<?php

namespace App\Http\Controllers\Proposal\Services\Bike;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';
include_once app_path() . '/Helpers/IcHelpers/GoDigitHelper.php';

use App\Http\Controllers\Proposal\ProposalController;
use App\Http\Controllers\SyncPremiumDetail\Bike\GodigitPremiumDetailController;
use Carbon\Carbon;
use App\Models\UserProposal;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use DateTime;
use App\Models\CkycGodigitFailedCasesData;
use App\Models\ProposalHash;
use App\Http\Controllers\Proposal\Services\Bike\V2\GoDigitSubmitProposal as oneapi;
use App\Models\CvAgentMapping;

use function PHPUnit\Framework\isEmpty;
class goDigitSubmitProposal
{
    public static function submit($proposal, $request)
    {
        if(strlen($proposal->engine_number) > 20)
        {
            return  [
                'premium_amount' => 0,
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
                return response()->json($responseArray);
            } else {
                $commonController = new \App\Http\Controllers\CommonController();
                $godigitKycStatus = $commonController->GodigitKycStatus(new \Illuminate\Http\Request([
                    'UserProductJourneyId' => customEncrypt($proposal->user_product_journey_id),
                    // 'enquiryId' => customEncrypt($proposal->user_product_journey_id),
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
        if($premium_type =='third_party_breakin'){
            $premium_type ='third_party';
        }
        if($premium_type =='own_damage_breakin'){
            $premium_type ='own_damage';
        }
        /* $check = strlen($proposal->engine_number);
        if($check < 6 || $check > 19)
        {
            return
            [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Chassis number should not be less than 6 digit or greater than 19 digit'
            ];
        }else{ */
        if($premium_type == 'third_party') 
        {
            $insurance_product_code = '20202';
            $previousNoClaimBonus = 'ZERO';
        }elseif($premium_type == 'own_damage')
        {
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
        }
        else
        {
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
        $motor_manf_year = $motor_manf_year_arr[1];
        $motor_manf_date = '01-'.$requestData->manufacture_year;
        $current_date = Carbon::now()->format('Y-m-d');
        // bike age calculation
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $bike_age = ceil($age / 12);
        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin' || $premium_type == "own_damage") 
        {
            $is_vehicle_new = 'false';
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $sub_insurance_product_code = 'PB';
            // $vehicle_registration_no  = str_replace("-", "", $requestData->vehicle_registration_no != "" ? $requestData->vehicle_registration_no : $proposal->vehicale_registration_number);
            $vehicle_registration_no  = explode("-", $proposal->vehicale_registration_number);

            if ($vehicle_registration_no[0] == 'DL') {
                $registration_no = RtoCodeWithOrWithoutZero($vehicle_registration_no[0].$vehicle_registration_no[1],true); 
                $vehicle_registration_no = str_replace("-", "", $registration_no).$vehicle_registration_no[2].$vehicle_registration_no[3];

            } else {
                $vehicle_registration_no = str_replace("-", "", $proposal->vehicale_registration_number);
            }
            if($requestData->business_type == 'breakin')
            {
                $breakin_make_time = strtotime('18:00:00');
                /* if($breakin_make_time > time())
                {
                   $policy_start_date = date('Y-m-d', strtotime('+1 day', time())); 
                }
                else
                {
                   $policy_start_date = date('Y-m-d', strtotime('+2 day', time())); 
                } */
                $policy_start_date = date('Y-m-d', strtotime('+2 day', time()));//godigit new IRDA policy start date logic

                if ($premium_type == 'third_party') {
                    $policy_start_date = date('Y-m-d', strtotime('+1 day', time()));
                }
            }
        }    
        else if ($requestData->business_type == 'newbusiness') 
        {
            $is_vehicle_new = 'true';
            $policy_start_date = Carbon::today()->format('Y-m-d');
            $sub_insurance_product_code = '51';
            $previousNoClaimBonus = 'ZERO';
            if($requestData->vehicle_registration_no == 'NEW')
            {
                $vehicle_registration_no  = str_replace("-", "", godigitRtoCode($requestData->rto_code));
            }
            else
            {
                $vehicle_registration_no  = str_replace("-", "", $requestData->vehicle_registration_no);
            }
        }
        /*if($requestData->previous_policy_type == 'Third-party' && $premium_type == 'third_party')
        {
            $policy_start_date = Carbon::today()->format('Y-m-d');
        }*/
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            $expdate=$requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
            $vehicle_in_90_days = $date_difference = get_date_diff('day',$expdate);

            if ($date_difference > 90) {  
                $previousNoClaimBonus = 'ZERO';
            }
        }
       /*  if(isset($requestData->voluntary_excess_value) && !empty($requestData->voluntary_excess_value))
        {
            $voluntary_deductible_amount = $voluntary_deductible[$requestData->voluntary_excess_value];
        }
        else
        {
            $voluntary_deductible_amount = 'ZERO';
        } */
        $voluntary_deductible_amount = 'ZERO';
        if(!empty($proposal->previous_insurance_company) && ($requestData->previous_policy_type != 'Not sure') && !empty($proposal->previous_policy_number))
        {
            $isPreviousInsurerKnown = "true";
        }
        else
        {
            $isPreviousInsurerKnown = "false";
        }

        $cng_lpg_amt = $non_electrical_amt = $electrical_amt = null;
        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                                        ->select('compulsory_personal_accident','applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                                        ->first();
        $is_tppd = false;
        $cpa_selected = 'false';
        $tenure = null;
        if(!empty($additional['compulsory_personal_accident']))
        {
            foreach ($additional['compulsory_personal_accident'] as $key => $data) 
            {
                if (isset($data['name']) && $data['name']  == 'Compulsory Personal Accident') 
                {
                    $cpa_selected = 'true';
                    $tenure = 1;
                    $tenure = isset($data['tenure'])? $data['tenure'] :$tenure;
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
        if (!empty($additional['discounts'])) {
            foreach ($additional['discounts'] as $data) {
                if ($data['name'] == 'TPPD Cover') {
                    $is_tppd = true;
                }
            }
        }
        $cover_pa_paid_driver = $cover_pa_unnamed_passenger = $cover_pa_paid_cleaner = $cover_pa_paid_conductor = null;
        $cleanerLL = false;
        $no_of_cleanerLL = NULL;
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
        $is_imt_23 = false;
        $rsa = false;
        $zero_dep = false;
        $engine_protection = false;
        $tyre_protection = false;
        $return_to_invoice = false;
        $consumables = false;
        if (!empty($additional['applicable_addons'])) {
            foreach ($additional['applicable_addons'] as $key => $data) {
                if ($data['name'] == 'Road Side Assistance' && $interval->y < 19) {
                    $rsa = true;
                }
                if ($data['name'] == 'Zero Depreciation' && $interval->y < 6) {
                    $zero_dep = true;
                }
                if ($data['name'] == 'Engine Protector' && $interval->y < 10) {
                    $engine_protection = true;
                }
                if ($data['name'] == 'Return To Invoice' && $interval->y < 3) {
                    $return_to_invoice = true;
                }

                if ($data['name'] == 'Consumable' && $interval->y < 6) {
                    $consumables = true;
                }
            }
        }
        if($requestData->business_type == 'breakin' || $requestData->previous_policy_type == 'Third-party')
        {
            $zero_dep = false;
            $rsa = false;
            $consumables = false;
            $return_to_invoice = false;
            $engine_protection = false;
        }
        if($requestData->business_type == 'newbusiness' && $premium_type == 'third_party' )
        {
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
            ->where('user_proposal_id',$proposal['user_proposal_id'])
            ->where('seller_type','P')
            ->first();

        $webUserId = config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID');
        $password = config('constants.IcConstants.godigit.GODIGIT_PASSWORD');

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000)
         {
            if($pos_data) {
                $credentials = getPospImdMapping([
                    'sellerType' => 'P',
                    'sellerUserId' => $pos_data->agent_id,
                    'productSubTypeId' => 2,
                    'ic_integration_type' => 'godigit'
                ]);
    
                if ($credentials['status'] ?? false) {
                    $webUserId = $credentials['data']['web_user_id'];
                    $password = $credentials['data']['password'];
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
        else if($is_pos_testing_mode == 'Y' && $quote->idv <= 5000000)
        {
            $is_pos = 'true';
            $posp_name = 'test';
            $posp_unique_number = '9768574564';
            $posp_pan_number = 'ABGTY8890Z';
            $posp_aadhar_number = '569278616999';
            $posp_contact_number = '9768574564';
        }
        if( isset($pos_data->category) && $pos_data->category == 'Essone')
        {
            $is_agent_float = 'Y';
        }
        $user_id = ($is_agent_float == 'Y') ? config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID_AGENT_FLOAT') : $webUserId;

        $password = ($is_agent_float == 'Y') ? config('constants.IcConstants.godigit.GODIGIT_PASSWORD_AGENT_FLOAT') : $password;
        //Quick Quote - Premium              
        $quote_premium_request = 
        [   'enquiryId' =>($is_agent_float == 'Y') ? $enquiry_id :(($premium_type == "own_damage") ? 'GODIGIT_QQ_TWO_WHEELER_SAOD_01': 'GODIGIT_QQ_TWO_WHEELER_PACKAGE_01'),
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
                            [
                            ],
                        ],
                        'surcharge' =>
                            [
                            'loadings' =>
                            [
                            ],
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
                'manufactureDate' =>  date('Y-m-d', strtotime('01-'.$requestData->manufacture_year)),
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
                //'isPreviousInsurerKnown' => empty($proposal->previous_insurance_company) || ($requestData->previous_policy_type == 'Third-party' && !in_array($premium_type, ['third_party', 'third_party_breakin'])) || ($requestData->business_type == 'breakin' && $date_difference > 90) || $requestData->previous_policy_expiry_date == 'New' ? 'false' : 'true',
                'isPreviousInsurerKnown' => $isPreviousInsurerKnown, //as per the git id #11182
                'previousInsurerCode' => isset($proposal->previous_insurance_company) ? $proposal->previous_insurance_company : "NA",
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
        ];
        if ($premium_type == "own_damage") {
            $quote_premium_request['previousInsurer']['originalPreviousPolicyType'] = "1OD_5TP";
            $quote_premium_request['previousInsurer']['currentThirdPartyPolicy']['isCurrentThirdPartyPolicyActive'] = true;
            $quote_premium_request['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyInsurerCode'] = $proposal->tp_insurance_company;
            $quote_premium_request['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyNumber'] = $proposal->tp_insurance_number;
            $quote_premium_request['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyStartDateTime'] = date('Y-m-d', strtotime($proposal->tp_start_date));
            $quote_premium_request['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyExpiryDateTime'] = date('Y-m-d', strtotime($proposal->tp_end_date));
        }
        if($isPreviousInsurerKnown == "false"){
            $quote_premium_request['previousInsurer']['isPreviousInsurerKnown'] = $isPreviousInsurerKnown;
            $quote_premium_request['previousInsurer']['previousInsurerCode'] = null;
            $quote_premium_request['previousInsurer']['previousPolicyNumber'] = null;
            $quote_premium_request['previousInsurer']['previousPolicyExpiryDate'] = null;
            $quote_premium_request['previousInsurer']['isClaimInLastYear'] = null;
            $quote_premium_request['previousInsurer']['originalPreviousPolicyType'] = null;
            $quote_premium_request['previousInsurer']['previousPolicyType'] = null;
            $quote_premium_request['previousInsurer']['previousNoClaimBonus'] = null;
            $quote_premium_request['previousInsurer']['currentThirdPartyPolicy'] = null;
           }
        $data = getWsData(config('constants.IcConstants.godigit.GODIGIT_BIKE_QUICK_QUOTE_PREMIUM'),$quote_premium_request, 'godigit',
        [
            'enquiryId' => $proposal->user_product_journey_id,
            'requestMethod' =>'post',
            'productName'  => $productData->product_name,
            'company'  => 'godigit',
            'method'   => 'Premium Calculation',
            'section' => $productData->product_sub_type_code,
            'webUserId' => $user_id,
            'password' => $password,
            'transaction_type' => 'proposal',
        ]);
        
        if (!empty($data)) 
        {
            $response = json_decode($data['response']);
            if ($response->error->errorCode == '0') 
            { 
                if(isset($response->motorBreakIn->isBreakin) && ($response->motorBreakIn->isBreakin == 1) && optional($response->preInspection)->isPreInspectionWaived == false && optional($response->preInspection)->isPreInspectionRequired == 'true')
                {
                    $self_inspection = 'true';
                    $is_breakin_case = 'Y';
                }
                else
                {
                    $is_breakin_case = 'N';
                }
               
                $address = DB::table('godigit_pincode_state_city_master as gdstcm')
                                ->where([   'pincode' => $proposal->pincode,
                                            'city' => $proposal->city,
                                            'state' => $proposal->state,
                                        ])
                                ->select('statecode','city', 'country', 'district')
                                ->first();
                
                $company = DB:: table('master_company')
                                    ->where('company_id' , '=' , $productData->product_sub_type_id)
                                    ->select('company_name')
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
                                "documentType"=> "GST",
                                "documentId"=> isset($proposal->gst_number) ? $proposal->gst_number : null,
                                "issuingAuthority"=> "IN",
                                "issuingPlace"=> null,
                                "issueDate"=> "",
                                "expiryDate"=> ""
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
                                    "state" => isset($address->statecode) ? trim($address->statecode) : null ,
                                    "city" => isset($address->city) ? trim($address->city) : null ,
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
                            "dateOfBirth" =>date('Y-m-d', strtotime($proposal->dob)),
                            "gender" => ($proposal->gender == 'MALE') ? 'MALE' : 'FEMALE',
                            "isDriver" => true,
                            "isInsuredPerson" => true
                        ];
                }
                $proposal_data = 
                [
                    "enquiryId" =>($is_agent_float == 'Y') ? $enquiry_id :(($premium_type == "own_damage") ? 'GODIGIT_QQ_TWO_WHEELER_SAOD_01': 'GODIGIT_QQ_TWO_WHEELER_PACKAGE_01'),
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
                        'manufactureDate' =>  date('Y-m-d', strtotime('01-'.$requestData->manufacture_year)),
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
                        //"isPreviousInsurerKnown" => empty($proposal->previous_insurance_company) || ($requestData->previous_policy_type == 'Third-party' && !in_array($premium_type, ['third_party', 'third_party_breakin'])) || ($requestData->business_type == 'breakin' && $date_difference > 90) || $requestData->previous_policy_expiry_date == 'New' ? 'false' : 'true',
                        "isPreviousInsurerKnown" => $isPreviousInsurerKnown,  //as per the git id #11182
                        "previousInsurerCode" => !empty($proposal->previous_insurance_company) ? $proposal->previous_insurance_company : null,
                        "previousPolicyNumber" => !empty($proposal->previous_policy_number) ? removeSpecialCharactersFromString($proposal->previous_policy_number) : null,
                        "previousPolicyExpiryDate" => !empty($proposal->prev_policy_expiry_date) ? date('Y-m-d', strtotime($proposal->prev_policy_expiry_date)) : null,
                        "isClaimInLastYear" => ($requestData->is_claim == 'Y') ? 'true' : 'false',
                        "originalPreviousPolicyType" =>$requestData->previous_policy_type == 'Third-party' ? '0OD_1TP' : '1OD_1TP',
                        "previousPolicyType" => $requestData->previous_policy_type == 'Third-party' ? '0OD_1TP' : '1OD_1TP',
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
                            if(!isset($icKycDoclist[$proposal->ckyc_type])) {
                                return [
                                    'status' => false,
                                    'message' => 'Something went wrong while doing the KYC. Please fill the KYC details again.'
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
                                        'errorType' => 'INFO',
                                        'status' => false,
                                        'message' => 'Please upload photograph to complete proposal.'
                                    ]; 
                                }else
                                {
                                    // $photo =\Illuminate\Support\Facades\Storage::get($photos_list[0]);
                                    $photo =ProposalController::getCkycDocument($photos_list[0]);
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
       
                            if($proposal->ckyc_type == 'aadhar_card') {
                                if(in_array(strtoupper($proposal->gender), ['M', 'MALE'])) {
                                    $gender = 'M';
                                }  else {
                                    $gender = 'W';
                                }
                                $kyc['gender'] = $gender;
                            }
                            $proposal_data['kyc'] = $kyc;
        
                        }else
                        {
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
                if($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true')
                {
                        $proposal_data['nominee']['firstName'] = $nominee_fname;
                        $proposal_data['nominee']['middleName']= '';
                        $proposal_data['nominee']['lastName'] = $nominee_lname;
                        $proposal_data['nominee']['dateOfBirth'] = date('Y-m-d', strtotime($proposal->nominee_dob));
                        $proposal_data['nominee']['relation'] = strtoupper($proposal->nominee_relationship);
                        $proposal_data['nominee']['personType'] = 'INDIVIDUAL';
                }
                if ($premium_type == "own_damage") {
                    $proposal_data['previousInsurer']['originalPreviousPolicyType'] = "1OD_5TP";
                    $proposal_data['previousInsurer']['currentThirdPartyPolicy']['isCurrentThirdPartyPolicyActive'] = true;
                    $proposal_data['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyInsurerCode'] = $proposal->tp_insurance_company;
                    $proposal_data['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyNumber'] = $proposal->tp_insurance_number;
                    $proposal_data['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyStartDateTime'] = date('Y-m-d', strtotime($proposal->tp_start_date));
                    $proposal_data['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyExpiryDateTime'] = date('Y-m-d', strtotime($proposal->tp_end_date));
                }

               if($isPreviousInsurerKnown == "false"){
                $proposal_data['previousInsurer']['isPreviousInsurerKnown'] = $isPreviousInsurerKnown;
                $proposal_data['previousInsurer']['previousInsurerCode'] = null;
                $proposal_data['previousInsurer']['previousPolicyNumber'] = null;
                $proposal_data['previousInsurer']['previousPolicyExpiryDate'] = null;
                $proposal_data['previousInsurer']['isClaimInLastYear'] = null;
                $proposal_data['previousInsurer']['originalPreviousPolicyType'] = null;
                $proposal_data['previousInsurer']['previousPolicyType'] = null;
                $proposal_data['previousInsurer']['previousNoClaimBonus'] = null;
                $proposal_data['previousInsurer']['currentThirdPartyPolicy'] = null;
               }
                $data = getWsData(config('constants.IcConstants.godigit.GODIGIT_BIKE_CREATE_QUOTE_PROPOSAL'),$proposal_data, 'godigit',
                [
                    'enquiryId' => $proposal->user_product_journey_id,
                    'requestMethod' =>'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'godigit',
                    'method'   => 'Proposal Submit',
                    'section' => $productData->product_sub_type_code,
                    'webUserId' => $user_id,
                    'password' => $password,
                    'transaction_type' => 'proposal',
                ]);
               
                if(!empty($data))
                {
                    set_time_limit(400);
                    $proposal_response = json_decode($data['response']);

                    if(empty($proposal_response))
                    {
                        return
                        [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'Insurer not reachable'
                        ];
                    }else if(!isset($proposal_response->error->errorCode) && isset($proposal_response->message))
                    {
                        return
                        [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => !empty($proposal_response->message) ? $proposal_response->message : 'Insurer not reachable'
                        ];
                    }
                    
                    if ($proposal_response->error->errorCode == '0') 
                    {        
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
                        $partsDepreciation=0;
                        $road_side_assistance=0;
                        $tppd = 0;
                        $engine_protection_amt = 0;
                        $consumables_amt = 0;
                        $return_to_invoice_amt = 0;

                        if ($is_breakin_case == 'Y') {
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => config('constants.IcConstants.godigit.BIKE_INSPECTION_ERROR_MESSAGE','Breakin Inspection required for this case, Kindly contact RM.')
                            ];
                        }

                        foreach ($contract->coverages as $key => $value) 
                        {
                            switch ($key) 
                            {
                                case 'thirdPartyLiability':

                                    if (isset($value->netPremium))
                                    {
                                        $tppd = (str_replace("INR ", "", $value->netPremium));
                                    }
                                    
                                break;
                    
                                case 'addons':
                                    if(isset($value->roadSideAssistance) && ($value->roadSideAssistance->selection == 'true'))
                                    {
                                        if(isset($value->roadSideAssistance->netPremium))
                                        {
                                            $road_side_assistance = str_replace("INR ", "", $value->roadSideAssistance->netPremium);
                                        }
                                    }
                                    if(isset($value->partsDepreciation) && ($value->partsDepreciation->selection == 'true'))
                                    {
                                        if(isset($value->partsDepreciation->netPremium))
                                        {
                                            $partsDepreciation = str_replace("INR ", "", $value->partsDepreciation->netPremium);
                                        }
                                    }
                                    if(isset($value->engineProtection) && ($value->engineProtection->selection == 'true'))
                                    {
                                        if(isset($value->engineProtection->netPremium))
                                        {
                                            $engine_protection_amt = str_replace("INR ", "", $value->engineProtection->netPremium);
                                        }
                                    }
                                    if(isset($value->returnToInvoice) && ($value->returnToInvoice->selection == 'true'))
                                    {
                                        if(isset($value->returnToInvoice->netPremium))
                                        {
                                            $return_to_invoice_amt = str_replace("INR ", "", $value->returnToInvoice->netPremium);
                                        }
                                    }
                                    if(isset($value->consumables) && ($value->consumables->selection == 'true'))
                                    {
                                        if(isset($value->consumables->netPremium))
                                        {
                                            $consumables_amt = str_replace("INR ", "", $value->consumables->netPremium);
                                        }
                                    }
                                break;
            
                                case 'ownDamage':
                                    
                                    if(isset($value->netPremium))
                                    {
                                         $od = str_replace("INR ", "", $value->netPremium);
                                         foreach ($value->discount->discounts as $key => $type) 
                                         {
                                             if ($type->discountType == "NCB_DISCOUNT") 
                                             {
                                                 $ncb_discount_amt = str_replace("INR ", "", $type->discountAmount);
                                             }
                                         }
                                    } 
                                break;
            
                                case 'legalLiability' :
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
                                    if (isset($value->selection) && ($value->selection == 1) && (isset($value->netPremium)))
                                    {
                                        $cover_pa_owner_driver_premium = (str_replace("INR ", "", $value->netPremium));
                                    } 
                                break;
            
                                case 'accessories' :
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
                        if(isset($cng_lpg_amt) && !empty($cng_lpg_amt))
                        {
                            $cng_lpg_tp = 60;
                            $tppd = $tppd - 60;
                        }

                        $addon_premium = 0;
                        $ncb_discount = $ncb_discount_amt;
                        $final_od_premium = $od;
                        $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium + $llcleaner_premium  + $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $cover_pa_paid_cleaner_premium + $cover_pa_paid_conductor_premium +$cover_pa_owner_driver_premium;
                        $addon_premium =$partsDepreciation + $road_side_assistance + $return_to_invoice_amt + $consumables_amt + $engine_protection_amt;
                        $final_total_discount = $ncb_discount + $voluntary_excess + $ic_vehicle_discount;
                        $final_net_premium  = str_replace("INR ", "", $proposal_response->netPremium);
                        $final_gst_amount = str_replace("INR ", "", $proposal_response->serviceTax->totalTax); // 18% IC 
                        $final_payable_amount = str_replace("INR ", "", $proposal_response->grossPremium); 
                        //$final_gst_amount      = ($final_net_premium * 0.18);
                        //$final_payable_amount  = $final_net_premium + $final_gst_amount;
                        //$final_net_premium  = ($final_od_premium + $final_tp_premium - $final_total_discount);

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
                        if($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true' && $requestData->business_type == 'newbusiness') 
                        {
                            $cpa_end_date=date('d-m-Y', strtotime('+3 year -1 day', strtotime($policy_start_date)));
                        }
                        else if($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true' && $requestData->business_type == 'rollover' || $requestData->business_type == 'breakin' ) {
                            $cpa_end_date =date('d-m-Y',strtotime($policy_end_date));
                        }
                        // $save = UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                        //                     ->update([
                        //                 'od_premium' => $final_od_premium - $final_total_discount,//+ $addon_premium,
                        //                 'tp_premium' => $final_tp_premium,
                        //                 'ncb_discount' => $ncb_discount,
                        //                 'addon_premium' => $addon_premium,
                        //                 'total_premium' => $final_net_premium,
                        //                 'service_tax_amount' => $final_gst_amount,
                        //                 'final_payable_amount' => $final_payable_amount,
                        //                 'cpa_premium' => $cover_pa_owner_driver_premium,
                        //                 //'policy_no' => $proposal_response->policyNumber,
                        //                 'proposal_no' => $proposal_response->policyNumber,
                        //                 'total_discount' => $final_total_discount,
                        //                 'unique_proposal_id' => $proposal_response->applicationId,
                        //                 'is_policy_issued' => $proposal_response->policyStatus,
                        //                 'policy_start_date' => date('d-m-Y',strtotime($policy_start_date)),
                        //                 'policy_end_date' => date('d-m-Y',strtotime($policy_end_date)),
                        //                 'unique_quote' => $enquiry_id,
                        //                 'tp_insurance_company' =>!empty($proposal->tp_insurance_company) ? $proposal->tp_insurance_company :'',
                        //                 'tp_insurance_company_name' => $additional_data['prepolicy']['tpInsuranceCompanyName'] ?? '',
                        //                 'tp_insurance_number' =>!empty($proposal->tp_insurance_number) ? $proposal->tp_insurance_number :'',
                        //                 'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime($policy_start_date)),
                        //                 'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+3 year -1 day', strtotime($policy_start_date))) : date('d-m-Y',strtotime($policy_end_date))),
                        //                 'cpa_start_date' => (($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true' ) ? date('d-m-Y',strtotime($policy_start_date)) :''),
                        //                 'cpa_end_date'   => $cpa_end_date,
                        //                 'is_cpa' => ($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true') ?'Y' : 'N',
                        //                 'ic_vehicle_details' => $vehicleDetails,
                        //                 'is_breakin_case' => $is_breakin_case,
                        //                 'is_inspection_done'=> $is_breakin_case,
                        //         ]);

                                $update_proposal=[
                                    'od_premium' => $final_od_premium - $final_total_discount,//+ $addon_premium,
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
                                    'policy_start_date' => date('d-m-Y',strtotime($policy_start_date)),
                                    'policy_end_date' => $requestData->business_type == 'newbusiness' && $premium_type == 'comprehensive' ? date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date))): ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party' ?  date('d-m-Y', strtotime('+5 year -1 day', strtotime($policy_start_date))): date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)))),
                                    'unique_quote' => $enquiry_id,
                                    'tp_insurance_company' =>!empty($proposal->tp_insurance_company) ? $proposal->tp_insurance_company :'',
                                    'tp_insurance_company_name' => $additional_data['prepolicy']['tpInsuranceCompanyName'] ?? '',
                                    'tp_insurance_number' =>!empty($proposal->tp_insurance_number) ? $proposal->tp_insurance_number :'',
                                    'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime($policy_start_date)),
                                    'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+3 year -1 day', strtotime($policy_start_date))) : date('d-m-Y',strtotime($policy_end_date))),
                                    'cpa_start_date' => (($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true' ) ? date('d-m-Y',strtotime($policy_start_date)) :''),
                                    'cpa_end_date'   => $cpa_end_date,
                                    'is_cpa' => ($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true') ?'Y' : 'N',
                                    'ic_vehicle_details' => $vehicleDetails,
                                    'is_breakin_case' => 'N'
                                    // 'is_inspection_done'=> $is_breakin_case,
                                ];
                                if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
                                    unset($update_proposal['tp_start_date']);
                                    unset($update_proposal['tp_end_date']);
                                }
                                $save = UserProposal::where('user_proposal_id', $proposal->user_proposal_id)->update($update_proposal);
                                
                            updateJourneyStage([
                                'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                                'ic_id' => $productData->company_id,
                                'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                'proposal_id' => $proposal->user_proposal_id,
                            ]);

                            GodigitPremiumDetailController::savePremiumDetails($data['webservice_id']);

                            if(config('constants.IS_CKYC_ENABLED_GODIGIT') === 'Y')
                            {
                                if(config('GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE') === 'Y')
                                {
                                    sleep(10);
                                    if (config('constants.IS_CKYC_ENABLED') == 'Y') 
                                    {
                                        $KycStatusApiResponse = GetKycStatusGoDIgit( $proposal->user_product_journey_id,$proposal_response->policyNumber,  $productData->product_sub_type_name,$proposal->user_proposal_id,customEncrypt( $proposal->user_product_journey_id));
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
                                            
                                            $message = godigitProposalKycMessage($proposal, $message);
        
                                            if((filter_var($KycStatusApiResponse['message'], FILTER_VALIDATE_URL)))
                                            {
                                                return response()->json([
                                                    'status' => true,
                                                    'msg' => "Proposal Submitted Successfully!",
                                                    'data' => [                            
                                                        'proposalId' => $proposal->user_proposal_id,
                                                        'userProductJourneyId' => $proposal->user_product_journey_id,
                                                        'proposalNo' => $proposal_response->applicationId,
                                                        'finalPayableAmount' => $final_payable_amount, 
                                                        'is_breakin' => 'N', //By default N as Breakin is not available 
                                                        'kyc_url' => $KycStatusApiResponse['message'],
                                                        'is_kyc_url_present' => true,
                                                        'kyc_message' => $message,
                                                        'kyc_status' => false,
                                                    ]
                                                ]);
                                            }else
                                            {
                                                // if no URL RETURNED BY IC FOR KYC
                                                return response()->json([
                                                    'status' => true,
                                                    'msg' => "Proposal Submitted Successfully!",
                                                    'data' => [                            
                                                        'proposalId' => $proposal->user_proposal_id,
                                                        'userProductJourneyId' => $proposal->user_product_journey_id,
                                                        'proposalNo' => $proposal_response->applicationId,
                                                        'finalPayableAmount' => $final_payable_amount, 
                                                        'is_breakin' => 'N',
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
                                            // Need to update the CKYC status for RB
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
                                'is_breakin' => 'N',
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
                            'message' => str_replace(",","",$proposal_response->error->validationMessages[0])
                        ];
                    } 
                    elseif(isset($proposal_response->error->errorCode) && $proposal_response->error->errorCode == '400')
                    {
                        return 
                        [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => str_replace(",","",$proposal_response->error->validationMessages[0])
                        ];
                    }
                }
                else 
                {
                    return
                    [
                        'premium_amount' => 0,
                        'status' => false,
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
                    'message' => str_replace(",","",$response->error->validationMessages[0])
                ];
            }
            elseif(isset($response->error->errorCode) && $response->error->errorCode == '400')
            {
                return 
                [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => str_replace(",","",$response->error->validationMessages[0])
                ];
            }   
        }
        else 
        {
            return
            [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Insurer not reachable'
            ];
        }
    // }
    }

    public static function renewalSubmit($proposal, $request)
    {
        if(strlen($proposal->engine_number) > 20)
        {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Engine Number Not Greater than 20 Characters'
            ];            
        }

        $requestData = getQuotation($proposal->user_product_journey_id);
        $enquiryId   = customDecrypt($request['enquiryId']);
        $productData = getProductDataByIc($request['policyId']);
        $enquiry_id = getUUID();
        $premium_type = DB::table('master_premium_type')
                                ->where('id', $productData->premium_type_id)
                                ->pluck('premium_type_code')
                                ->first();
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $bike_age = ceil($age / 12);
        if($premium_type =='third_party_breakin'){
            $premium_type ='third_party';
        }
        if($premium_type =='own_damage_breakin'){
            $premium_type ='own_damage';
        }
        $policy_holder_type = ($requestData->vehicle_owner_type == "I" ? "INDIVIDUAL" : "COMPANY");
        $cpa_selected = 'false';
        
        $mmv = get_mmv_details($productData, $requestData->version_id, 'godigit');
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];

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

        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        
        $fetch_url = config('constants.IcConstants.godigit.GODIGIT_BIKE_MOTOR_FETCH_RENEWAL_URL').$user_proposal['previous_policy_number'];

        $posData = CvAgentMapping::where([
            'user_product_journey_id' => $enquiryId,
            'seller_type' => 'P'
        ])
        ->first();

        $webUserId = config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID');
        $password = config('constants.IcConstants.godigit.GODIGIT_PASSWORD');

        if (!empty($posData)) {
            $credentials = getPospImdMapping([
                'sellerType' => 'P',
                'sellerUserId' => $posData->agent_id,
                'productSubTypeId' => $productData->product_sub_type_id,
                'ic_integration_type' => $productData->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
            ]);
        
            if ($credentials['status'] ?? false) {
                $webUserId = $credentials['data']['web_user_id'];
                $password = $credentials['data']['password'];
            }
        }
    
        $data = getWsData($fetch_url,[], 'godigit', [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'get',
            'productName'       => $productData->product_name,
            'company'           => 'godigit',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Fetch Policy Details',
            'transaction_type'  => 'proposal',
            'webUserId' => $webUserId,
            'password' => $password,
        ]);  
        $response_data = json_decode($data['response']);  
        $isAdditionRemovalAllowed = config('IS_ADDTION_REMOVAL_ADDON_ALLOWED', 'N') == 'Y' ? 'true' : null;

        if(isset($response_data->error->errorCode) && $response_data->error->errorCode == '0')
        {
    
            
            $cpa_end_date = '';

            if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin' || $premium_type == "own_damage") 
            {
                $is_vehicle_new = 'false';
                $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($response_data->contract->endDate)));

                $sub_insurance_product_code = 'PB';
                
                $vehicle_registration_no = (config('constants.IcConstants.godigit.GODIGIT_RENEWAL_KEEP_EXISTING_REG_NO') === 'Y') ? $user_proposal->vehicale_registration_number : $response_data->vehicle->licensePlateNumber;
               
                if($requestData->business_type == 'breakin')
                {
                    /*return
                    [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Your Policy has already been expired'
                    ];*/
                    $breakin_make_time = strtotime('18:00:00');
                    if($breakin_make_time > time())
                    {
                       $policy_start_date = date('Y-m-d', strtotime('+1 day', time())); 
                    }
                    else
                    {
                       $policy_start_date = date('Y-m-d', strtotime('+2 day', time())); 
                    }
                }
            }  
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
            if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
                $expdate=$requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
                $vehicle_in_90_days = $date_difference = get_date_diff('day',$expdate);

                if ($date_difference > 90) {  
                    $previousNoClaimBonus = 'ZERO';
                }
            }

            if($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true' && $requestData->business_type == 'newbusiness') 
            {
                $cpa_end_date=date('d-m-Y', strtotime('+3 year -1 day', strtotime($policy_start_date)));
            }
            else if($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true' && $requestData->business_type == 'rollover' || $requestData->business_type == 'breakin' ) {
                $cpa_end_date =date('d-m-Y',strtotime($policy_end_date));
            }
    
            if($response_data->contract->insuranceProductCode == '20201')
            {
               $policyType = 'Comprehensive'; 
            }
            else if ($response_data->contract->insuranceProductCode == '20203')
            {
                $policyType = 'Own Damage';
            }
            else if($response_data->contract->insuranceProductCode == '20202')
            {
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
            $llpaiddriver = false;
            $cover_pa_owner_driver = false;
            $cover_pa_paid_drive= false;
            $zero_depreciation_claimsCovered = null;
            $discountPercent = 0;


    
            foreach ($contract->coverages as $key => $value) 
            {
                switch ($key) 
                {
                    case 'thirdPartyLiability':
    
                        if (isset($value->netPremium))
                        {
                            $tppd = true;
                        }
                        
                    break;
        
                    case 'addons':
                        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                            ->select('compulsory_personal_accident', 'applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                            ->first();
                        foreach ($value as $key => $addon) {
                            switch ($key) {
                                case 'partsDepreciation':
                                    if (($isAdditionRemovalAllowed ?? $addon->selection) == 'true' && ($addon->coverAvailability ?? '') == 'AVAILABLE') {
                                        $zero_depreciation = true;
                                        $zero_depreciation_claimsCovered = $addon->claimsCovered;
                                        if ($isAdditionRemovalAllowed) {
                                            if (array_search('Zero Depreciation', array_column($additional['applicable_addons'],'name')) === false) {
                                                $zero_depreciation = false;
                                                $zero_depreciation_claimsCovered = null;
                                            }
                                        }
                                    }
                                    break;
    
                                case 'roadSideAssistance':
                                    if (($isAdditionRemovalAllowed ?? $addon->selection) == 'true' && ($addon->coverAvailability ?? '') == 'AVAILABLE') {
                                        $road_side_assistance = true;
                                        if ($isAdditionRemovalAllowed) {
                                            if (array_search('Road Side Assistance', array_column($additional['applicable_addons'],'name')) === false) {
                                                $road_side_assistance = false;
                                            }
                                        }
                                    }
                                    break;
    
                                case 'engineProtection':
                                    if (($isAdditionRemovalAllowed ?? $addon->selection) == 'true' && ($addon->coverAvailability ?? '') == 'AVAILABLE') {
                                        $engine_protection = true;
                                        if ($isAdditionRemovalAllowed) {
                                            if (array_search('Engine Protector', array_column($additional['applicable_addons'],'name')) === false) {
                                                $engine_protection = false;
                                            }
                                        }
                                    }
                                    break;
    
                                case 'returnToInvoice':
                                    if (($isAdditionRemovalAllowed ?? $addon->selection) == 'true' && ($addon->coverAvailability ?? '') == 'AVAILABLE') {
                                        $return_to_invoice = true;
                                        if ($isAdditionRemovalAllowed) {
                                            if (array_search('Return To Invoice', array_column($additional['applicable_addons'],'name')) === false) {
                                                $return_to_invoice = false;
                                            }
                                        }
                                    }
                                    break;
    
                                case 'consumables':
                                    if (($isAdditionRemovalAllowed ?? $addon->selection) == 'true' && ($addon->coverAvailability ?? '') == 'AVAILABLE') {
                                        $consumables = true;
                                        if ($isAdditionRemovalAllowed) {
                                            if (array_search('Consumable', array_column($additional['applicable_addons'],'name')) === false) {
                                                $consumables = false;
                                            }
                                        }
                                    }
                                    break;
    
                                }
                            }
                    break;
    
                    case 'ownDamage':
                       
                       if(isset($value->netPremium))
                       {
                            $od = (str_replace("INR ", "", $value->netPremium));
                            foreach ($value->discount->discounts as $key => $type) 
                            {
                                if ($type->discountType == "NCB_DISCOUNT") 
                                {
                                    $discountPercent = ($type->discountPercent);
                                }
                            }
                       } 
                    break;
    
                    case 'legalLiability' :
                        foreach ($value as $cover => $subcover) {
                            if ($cover == "paidDriverLL") {
                                if($subcover->selection == 1) {
                                    $llpaiddriver = true;
                                }
                            }
                        }
                    break;
                
                    case 'personalAccident':
                        // By default Complusory PA Cover for Owner Driver
                        if (isset($value->selection) && ($value->selection == 1) && (isset($value->netPremium)))
                        {
                            $cover_pa_owner_driver= true;
                            $cpa_selected = 'true';
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
                                        $cover_pa_paid_drive= true;
                                    }
                                }
                            }
                        }
                    break;
                }
            }

            $cng_lpg_amt = $non_electrical_amt = $electrical_amt = null;
            $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                                            ->select('compulsory_personal_accident','applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                                            ->first();
            $is_tppd = false;
            $cpa_selected = 'false';
            $tenure = null;
            if(!empty($additional['compulsory_personal_accident']))
            {
                foreach ($additional['compulsory_personal_accident'] as $key => $data) 
                {
                    if (isset($data['name']) && $data['name']  == 'Compulsory Personal Accident') 
                    {
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
            $cover_pa_paid_driver = $cover_pa_unnamed_passenger = $cover_pa_paid_cleaner = $cover_pa_paid_conductor = null;
            $cleanerLL = false;
            $no_of_cleanerLL = NULL;
            $no_of_driverLL = 0;
            $paidDriverLL = "false";
            if (!empty($additional['additional_covers'])) {
                foreach ($additional['additional_covers'] as $data) {
                    if ($data['name'] == 'LL paid driver') {
                        $no_of_driverLL = 1;
                        $paidDriverLL = "true";
                    }

                    if (($data['name'] ?? '')  == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                        $cover_pa_paid_driver = $data['sumInsured'];
                    }
        
                    if (($data['name'] ?? '')  == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                        $cover_pa_unnamed_passenger = $data['sumInsured'];
                    }
                }
            }
            $is_imt_23 = false;
            $rsa = false;
            $zero_dep = false;
            $engine_protection = false;
            $tyre_protection = false;
            $return_to_invoice = false;
            $consumables = false;
            if (!empty($additional['applicable_addons'])) {
                // foreach ($additional['addons'] as $key => $data) {
                //     if ($data['name'] == 'Road Side Assistance' && $interval->y < 19) {
                //         $rsa = true;
                //     }
                //     if ($data['name'] == 'Zero Depreciation' && $interval->y < 6) {
                //         $zero_dep = true;
                //     }
                //     if ($data['name'] == 'Engine Protector' && $interval->y < 10) {
                //         $engine_protection = true;
                //     }
                //     if ($data['name'] == 'Return To Invoice' && $interval->y < 3) {
                //         $return_to_invoice = true;
                //     }

                //     if ($data['name'] == 'Consumable' && $interval->y < 6) {
                //         $consumables = true;
                //     }
                // }
            }
            if($requestData->business_type == 'breakin' || $requestData->previous_policy_type == 'Third-party')
            {
                $zero_dep = false;
                $rsa = false;
                $consumables = false;
                $return_to_invoice = false;
                $engine_protection = false;
            }
    
            $enquiryId_digit = ($premium_type == "own_damage") ? 'GODIGIT_QQ_TWO_WHEELER_SAOD_01': 'GODIGIT_QQ_TWO_WHEELER_PACKAGE_01';
            $premium_calculation_array = [
                "enquiryId" => isset($response_data->enquiryId ) ? $response_data->enquiryId : $enquiryId_digit,
                "contract" => [
                    "insuranceProductCode" => $response_data->contract->insuranceProductCode,
                    "subInsuranceProductCode" => $response_data->contract->subInsuranceProductCode,
                    "startDate" => $policy_start_date,
                    "endDate" => $policy_end_date,
                    "policyHolderType" => $response_data->contract->policyHolderType,
                    "coverages" => [
                        "voluntaryDeductible" => "ZERO",
                        "thirdPartyLiability" => ["isTPPD" => false],
                        "ownDamage" => [
                            "discount" => [
                                "userSpecialDiscountPercent" => 0,
                                "discounts" => [],
                            ],
                            "surcharge" => ["loadings" => []],
                        ],
                        "personalAccident" => [
                            "selection" => $cover_pa_owner_driver,
                            "insuredAmount" => ($cover_pa_owner_driver == true) ? 1500000 : 0,
                            "coverTerm" => null,
                        ],
                        "accessories" => [
                            "cng" => ["selection" => null, "insuredAmount" => null],
                            "electrical" => ["selection" => null, "insuredAmount" => null],
                            "nonElectrical" => [
                                "selection" => null,
                                "insuredAmount" => null,
                            ],
                        ],
                        
                    "addons" => [
                            "partsDepreciation" => [
                                "claimsCovered" => $zero_depreciation_claimsCovered,
                                "selection" => $zero_depreciation,
                            ],
                            "roadSideAssistance" => ["selection" => $road_side_assistance],
                            "engineProtection" => ["selection" => $engine_protection],
                            "tyreProtection" => ["selection" => null],
                            "rimProtection" => ["selection" => null],
                            "returnToInvoice" => ["selection" =>$return_to_invoice],
                            "consumables" => ["selection" => $consumables],
                        ],
                        "legalLiability" => [
                            "paidDriverLL" => ["selection" => $llpaiddriver, "insuredCount" => ($llpaiddriver == true) ? 1 : 0 ],
                            "employeesLL" => ["selection" => null, "insuredCount" => null],
                            "unnamedPaxLL" => ["selection" => null, "insuredCount" => null],
                            "cleanersLL" => ["selection" => null, "insuredCount" => null],
                            "nonFarePaxLL" => ["selection" => null, "insuredCount" => null],
                            "workersCompensationLL" => [
                                "selection" => null,
                                "insuredCount" => null,
                            ],
                        ],
                        "unnamedPA" => [
                            "unnamedPax" => [
                                "selection" => null,
                                "insuredAmount" => null,
                                "insuredCount" => null,
                            ],
                            "unnamedPaidDriver" => [
                                "selection" => null,
                                "insuredAmount" => null,
                                "insuredCount" => null,
                            ],
                            "unnamedHirer" => [
                                "selection" => null,
                                "insuredAmount" => null,
                                "insuredCount" => null,
                            ],
                            "unnamedPillionRider" => [
                                "selection" => null,
                                "insuredAmount" => null,
                                "insuredCount" => null,
                            ],
                            "unnamedCleaner" => [
                                "selection" => null,
                                "insuredAmount" => null,
                            ],
                            "unnamedConductor" => [
                                "selection" => null,
                                "insuredAmount" => null,
                                "insuredCount" => null,
                            ],
                        ],
                    ],
                ],
                "vehicle" => [
                    "isVehicleNew" => $response_data->vehicle->isVehicleNew ?? '',
                    "vehicleMaincode" => $response_data->vehicle->vehicleMaincode ?? '',
                    "licensePlateNumber" => str_replace("-","",$vehicle_registration_no),
                    "registrationAuthority" => $response_data->vehicle->registrationAuthority ?? '',
                    "vehicleIdentificationNumber" => $response_data->vehicle->vehicleIdentificationNumber ?? '',
                    "manufactureDate" => $response_data->vehicle->manufactureDate ?? '',
                    "registrationDate" => $response_data->vehicle->registrationDate ?? '',
                    "vehicleIDV" => [
                        "idv" => $response_data->vehicle->vehicleIDV->idv ?? '',
                        "defaultIdv" => $response_data->vehicle->vehicleIDV->defaultIdv ?? '',
                        "minimumIdv" => $response_data->vehicle->vehicleIDV->minimumIdv ?? '',
                        "maximumIdv" => $response_data->vehicle->vehicleIDV->maximumIdv ?? '',
                    ],
                    "trailers" => [],
                    "make" => $response_data->vehicle->make ?? '',
                    "model" => $response_data->vehicle->model ?? '',
                ],
                "previousInsurer" => [
                    "isPreviousInsurerKnown" => true,
                    "previousInsurerCode" => "158",
                    "previousPolicyNumber" => $response_data->policyNumber ?? '',
                    "previousPolicyExpiryDate" => $response_data->contract->endDate ?? '',
                    "isClaimInLastYear" => false,
                    "previousNoClaimBonus" => $response_data->contract->currentNoClaimBonus ?? '',
                ],
                "pospInfo" => [
                    'isPOSP' => $response_data->pospInfo->isPOSP,
                    'pospName' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospName : "",
                    'pospUniqueNumber' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospUniqueNumber : "",
                    'pospLocation' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospLocation : "",
                    'pospPanNumber' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospPanNumber : "",
                    'pospAadhaarNumber' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospAadhaarNumber : "",
                    'pospContactNumber' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospContactNumber : "",
                ],
                "pincode" => isset($response_data->persons[0]->addresses[0]->pincode) ? $response_data->persons[0]->addresses[0]->pincode : "400001",
            ];

            if ($premium_type == "own_damage") {
            $premium_calculation_array['previousInsurer']['originalPreviousPolicyType'] = "1OD_5TP";
            $premium_calculation_array['previousInsurer']['currentThirdPartyPolicy']['isCurrentThirdPartyPolicyActive'] = true;
            $premium_calculation_array['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyInsurerCode'] = ($response_data->previousInsurer->currentThirdPartyPolicy->currentThirdPartyPolicyInsurerCode) ?? $proposal->tp_insurance_company;
            $premium_calculation_array['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyNumber'] = ($response_data->previousInsurer->currentThirdPartyPolicy->currentThirdPartyPolicyNumber) ?? $proposal->tp_insurance_number;
            $premium_calculation_array['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyStartDateTime'] = ($response_data->previousInsurer->currentThirdPartyPolicy->currentThirdPartyPolicyStartDateTime) ?? date('Y-m-d', strtotime($proposal->tp_start_date));
            $premium_calculation_array['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyExpiryDateTime'] = ($response_data->previousInsurer->currentThirdPartyPolicy->currentThirdPartyPolicyExpiryDateTime) ?? date('Y-m-d', strtotime($proposal->tp_end_date));
        }
        if ($isAdditionRemovalAllowed) {
            $premium_calculation_array['contract']['coverages']['legalLiability']['paidDriverLL']['selection'] = $paidDriverLL;
            $premium_calculation_array['contract']['coverages']['legalLiability']['paidDriverLL']['insuredCount'] = $no_of_driverLL;

            $premium_calculation_array['contract']['coverages']['unnamedPA']['unnamedPax']['selection'] = !empty($cover_pa_unnamed_passenger) ? 'true' : 'false';
            $premium_calculation_array['contract']['coverages']['unnamedPA']['unnamedPax']['insuredAmount'] = !empty($cover_pa_unnamed_passenger) ? $cover_pa_unnamed_passenger : 0;

            $premium_calculation_array['contract']['coverages']['unnamedPA']['unnamedPaidDriver']['selection'] = !empty($cover_pa_paid_driver) ? 'true' : 'false';
            $premium_calculation_array['contract']['coverages']['unnamedPA']['unnamedPaidDriver']['insuredAmount'] = !empty($cover_pa_paid_driver) ? $cover_pa_paid_driver : 0;

            $premium_calculation_array['contract']['coverages']['thirdPartyLiability']['isTPPD'] = $is_tppd;
        }
            
                $data = getWsData(config('constants.IcConstants.godigit.GODIGIT_BIKE_QUICK_QUOTE_PREMIUM'),$premium_calculation_array, 'godigit',
                [
                    'enquiryId' => $enquiryId,
                    'requestMethod' =>'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'godigit',
                    'section' => $productData->product_sub_type_code,
                    'method' =>'Premium Calculation Renewal',
                    'webUserId' => $webUserId,
                    'password' => $password,
                    'transaction_type' => 'proposal',
                ]);
            
            if (!empty($data)) 
            {
                $response = json_decode($data['response']);
                if (isset($response->error->errorCode) && $response->error->errorCode == '0') 
                {
                    $contract = $response_data->contract;
                    
                    if(isset($response->motorBreakIn->isBreakin) && ($response->motorBreakIn->isBreakin == 1))
                    {
                        $self_inspection = 'true';
                        $is_breakin_case = 'N';
                    }
                    else
                    {
                        $is_breakin_case = 'N';
                    }
                    foreach ($contract->coverages as $key => $value) 
                    {
                        switch ($key) 
                        {
                            case 'thirdPartyLiability':
            
                                if (isset($value->netPremium))
                                {
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
                               
                               if(isset($value->netPremium))
                               {
                                    $od = (str_replace("INR ", "", $value->netPremium));
                                    foreach ($value->discount->discounts as $key => $type) 
                                    {
                                        if ($type->discountType == "NCB_DISCOUNT") 
                                        {
                                            $discountPercent = ($type->discountPercent);
                                        }
                                    }
                               } 
                            break;
            
                            case 'legalLiability' :
                                foreach ($value as $cover => $subcover) {
                                    if ($cover == "paidDriverLL") {
                                        if($subcover->selection == 1) {
                                            $llpaiddriver = true;
                                        }
                                    }
                                }
                            break;
                        
                            case 'personalAccident':
                                // By default Complusory PA Cover for Owner Driver
                                if (isset($value->selection) && ($value->selection == 1) && (isset($value->netPremium)))
                                {
                                    $cover_pa_owner_driver= true;
                                    $cpa_selected = 'true';
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
                                                $cover_pa_paid_drive= true;
                                            }
                                        }
                                    }
                                }
                            break;
                        }
                    }
                        // dd($response_data->previousInsurer);
                    $enquiryId_digit = ($premium_type == "own_damage") ? 'GODIGIT_QQ_TWO_WHEELER_SAOD_01': 'GODIGIT_QQ_TWO_WHEELER_PACKAGE_01';

                    $proposal_data = [
                                        "enquiryId" => isset($response_data->enquiryId ) ? $response_data->enquiryId : $enquiryId_digit,
                                        "contract" => [
                                            "insuranceProductCode" =>$response_data->contract->insuranceProductCode,
                                            "subInsuranceProductCode" => $response_data->contract->subInsuranceProductCode,
                                            "startDate" => $policy_start_date,
                                            "endDate" => $policy_end_date,
                                            "policyHolderType" => $response_data->contract->policyHolderType,
                                            "quotationDate" => date('Y-m-d'),//"2022-04-18",
                                            "coverages" => [
                                                "voluntaryDeductible" => "ZERO",
                                                "thirdPartyLiability" => ["isTPPD" => false],
                                                "ownDamage" => [
                                                    "discount" => [
                                                        "userSpecialDiscountPercent" => 0,
                                                        "discounts" => [],
                                                    ],
                                                    "surcharge" => ["loadings" => []],
                                                ],
                                                "personalAccident" => [
                                                    "selection" => $cover_pa_owner_driver,
                                                    "insuredAmount" => ($cover_pa_owner_driver == true) ? 1500000 : 0,
                                                    "coverTerm" => null,
                                                ],
                                                "accessories" => [
                                                    "cng" => ["selection" => null, "insuredAmount" => null],
                                                    "electrical" => ["selection" => null, "insuredAmount" => null],
                                                    "nonElectrical" => [
                                                        "selection" => null,
                                                        "insuredAmount" => null,
                                                    ],
                                                ],
                                                "addons" => [
                                                    "partsDepreciation" => [
                                                        "claimsCovered" => $zero_depreciation_claimsCovered,
                                                        "selection" => $zero_depreciation,
                                                    ],
                                                    "roadSideAssistance" => ["selection" => $road_side_assistance],
                                                    "engineProtection" => ["selection" => $engine_protection],
                                                    "tyreProtection" => ["selection" => null],
                                                    "rimProtection" => ["selection" => null],
                                                    "returnToInvoice" => ["selection" =>$return_to_invoice],
                                                    "consumables" => ["selection" => $consumables],
                                                ],
                                                "legalLiability" => [
                                                    "paidDriverLL" => ["selection" => $llpaiddriver, "insuredCount" => ($llpaiddriver == true) ? 1 : 0 ],
                                                    "employeesLL" => ["selection" => null, "insuredCount" => null],
                                                    "unnamedPaxLL" => ["selection" => null, "insuredCount" => null],
                                                    "cleanersLL" => ["selection" => null, "insuredCount" => null],
                                                    "nonFarePaxLL" => ["selection" => null, "insuredCount" => null],
                                                    "workersCompensationLL" => [
                                                        "selection" => null,
                                                        "insuredCount" => null,
                                                    ],
                                                ],
                                                "unnamedPA" => [
                                                    "unnamedPax" => [
                                                        "selection" => null,
                                                        "insuredAmount" => null,
                                                        "insuredCount" => null,
                                                    ],
                                                    "unnamedPaidDriver" => [
                                                        "selection" => null,
                                                        "insuredAmount" => null,
                                                        "insuredCount" => null,
                                                    ],
                                                    "unnamedHirer" => [
                                                        "selection" => null,
                                                        "insuredAmount" => null,
                                                        "insuredCount" => null,
                                                    ],
                                                    "unnamedPillionRider" => [
                                                        "selection" => null,
                                                        "insuredAmount" => null,
                                                        "insuredCount" => null,
                                                    ],
                                                    "unnamedCleaner" => [
                                                        "selection" => null,
                                                        "insuredAmount" => null,
                                                    ],
                                                    "unnamedConductor" => [
                                                        "selection" => null,
                                                        "insuredAmount" => null,
                                                        "insuredCount" => null,
                                                    ],
                                                ],
                                            ],
                                        ],
                                        "persons" => [
                                            [
                                                "personType" => $response_data->persons[0]->personType,
                                                "addresses" => [
                                                    [
                                                        "addressType" => $response_data->persons[0]->addresses[0]->addressType,
                                                        "state" => $response_data->persons[0]->addresses[0]->state,
                                                        "city" => $response_data->persons[0]->addresses[0]->city,
                                                        "country" => $response_data->persons[0]->addresses[0]->country,
                                                        "pincode" => $response_data->persons[0]->addresses[0]->pincode,
                                                    ],
                                                ],
                                                "communications" => [
                                                    [
                                                        "communicationType" => $response_data->persons[0]->communications[0]->communicationType,
                                                        "communicationId" => $response_data->persons[0]->communications[0]->communicationId,
                                                        "isPrefferedCommunication" => $response_data->persons[0]->communications[0]->isPrefferedCommunication,
                                                    ],
                                                    [
                                                        "communicationType" => $response_data->persons[0]->communications[1]->communicationType,
                                                        "communicationId" => $response_data->persons[0]->communications[1]->communicationId,
                                                        "isPrefferedCommunication" => false,
                                                    ],
                                                ],
                                                "identificationDocuments" => [],
                                                "isPolicyHolder" => true,
                                                "isVehicleOwner" => true,
                                                "firstName" => $response_data->persons[0]->firstName,
                                                "lastName" => $response_data->persons[0]->lastName,
                                                "dateOfBirth" => $response_data->persons[0]->dateOfBirth,
                                                "gender" => $response_data->persons[0]->gender,
                                                "isDriver" => $response_data->persons[0]->isDriver ?? '',
                                                "isInsuredPerson" => $response_data->persons[0]->isInsuredPerson ?? '',
                                            ],
                                        ],
                                        "vehicle" => [
                                            "isVehicleNew" => $response_data->vehicle->isVehicleNew ?? '',
                                            "vehicleMaincode" => $response_data->vehicle->vehicleMaincode ?? '',
                                            "licensePlateNumber" => str_replace("-","",$vehicle_registration_no),
                                            "registrationAuthority" => $response_data->vehicle->registrationAuthority ?? '',
                                            "vehicleIdentificationNumber" => $response_data->vehicle->vehicleIdentificationNumber ?? '',
                                            "manufactureDate" => $response_data->vehicle->manufactureDate ?? '',
                                            "registrationDate" => $response_data->vehicle->registrationDate ?? '',
                                            "vehicleIDV" => [
                                                "idv" => $response_data->vehicle->vehicleIDV->idv ?? '',
                                                "defaultIdv" => $response_data->vehicle->vehicleIDV->defaultIdv ?? '',
                                                "minimumIdv" => $response_data->vehicle->vehicleIDV->minimumIdv ?? '',
                                                "maximumIdv" => $response_data->vehicle->vehicleIDV->maximumIdv ?? '',
                                            ],
                                            "trailers" => [],
                                            "make" => $response_data->vehicle->make ?? '',
                                            "model" => $response_data->vehicle->model ?? '',
                                        ],
                                        "previousInsurer" => [
                                            "isPreviousInsurerKnown" => true,
                                            "previousInsurerCode" => "158",
                                            "previousPolicyNumber" => $response_data->policyNumber ?? '',
                                            "previousPolicyExpiryDate" => $response_data->contract->endDate ?? '',
                                            "isClaimInLastYear" => false,
                                            "previousNoClaimBonus" => $response_data->contract->currentNoClaimBonus ?? '',
                                        ],
                                        "pospInfo" => [
                                                        'isPOSP' => $response_data->pospInfo->isPOSP,
                                                        'pospName' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospName : "",
                                                        'pospUniqueNumber' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospUniqueNumber : "",
                                                        'pospLocation' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospLocation : "",
                                                        'pospPanNumber' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospPanNumber : "",
                                                        'pospAadhaarNumber' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospAadhaarNumber : "",
                                                        'pospContactNumber' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospContactNumber : "",
                                        ],
                                        "preInspection" => [
                                            "isPreInspectionOpted" => false,
                                            "isPreInspectionRequired" => false,
                                            "isPreInspectionEligible" => true,
                                            "isPreInspectionEligibleWithZeroDep" => true,
                                            "isPreInspectionEligibleWithoutZeroDep" => true,
                                            "isPreInspectionWaived" => false,
                                            "isSchoolBusWaiverEligibleWithTPlusFortyEight" => false,
                                            "preInspectionReasons" => [],
                                        ],
                                        "motorTransits" => [],
                                        "pincode" => isset($response_data->persons[0]->addresses[0]->pincode) ? $response_data->persons[0]->addresses[0]->pincode : "",
                                        "motorBreakIn" => [
                                            "isBreakin" => false,
                                            "isPreInspectionWaived" => false,
                                            "isPreInspectionCompleted" => false,
                                        ],
                                    ];

                                    if ($isAdditionRemovalAllowed) {
                                        $proposal_data['contract']['coverages']['legalLiability']['paidDriverLL']['selection'] = $paidDriverLL;
                                        $proposal_data['contract']['coverages']['legalLiability']['paidDriverLL']['insuredCount'] = $no_of_driverLL;
                        
                                        $proposal_data['contract']['coverages']['unnamedPA']['unnamedPax']['selection'] = !empty($cover_pa_unnamed_passenger) ? 'true' : 'false';
                                        $proposal_data['contract']['coverages']['unnamedPA']['unnamedPax']['insuredAmount'] = !empty($cover_pa_unnamed_passenger) ? $cover_pa_unnamed_passenger : 0;
                        
                                        $proposal_data['contract']['coverages']['unnamedPA']['unnamedPaidDriver']['selection'] = !empty($cover_pa_paid_driver) ? 'true' : 'false';
                                        $proposal_data['contract']['coverages']['unnamedPA']['unnamedPaidDriver']['insuredAmount'] = !empty($cover_pa_paid_driver) ? $cover_pa_paid_driver : 0;
                        
                                        $proposal_data['contract']['coverages']['thirdPartyLiability']['isTPPD'] = $is_tppd;
                                    }

                                    if ($premium_type == "own_damage") {
                                        $proposal_data['previousInsurer']['originalPreviousPolicyType'] = "1OD_5TP";
                                        $proposal_data['previousInsurer']['currentThirdPartyPolicy']['isCurrentThirdPartyPolicyActive'] = true;
                                        $proposal_data['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyInsurerCode'] = ($response_data->previousInsurer->currentThirdPartyPolicy->currentThirdPartyPolicyInsurerCode) ?? $proposal->tp_insurance_company;
                                        $proposal_data['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyNumber'] = ($response_data->previousInsurer->currentThirdPartyPolicy->currentThirdPartyPolicyNumber) ?? $proposal->tp_insurance_number;
                                        $proposal_data['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyStartDateTime'] = ($response_data->previousInsurer->currentThirdPartyPolicy->currentThirdPartyPolicyStartDateTime) ?? date('Y-m-d', strtotime($proposal->tp_start_date));
                                        $proposal_data['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyExpiryDateTime'] = ($response_data->previousInsurer->currentThirdPartyPolicy->currentThirdPartyPolicyExpiryDateTime) ?? date('Y-m-d', strtotime($proposal->tp_end_date));
                                    }

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
                                                        // $photo = \Illuminate\Support\Facades\Storage::get($photos_list[0]);
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
                                                $proposal_data['kyc'] = $kyc;
                                            } else {
                                                return
                                                    [
                                                        'status' => false,
                                                        'message' => "CKYC verification needed"
                                                    ];
                                            }
                                        }
                                    }
                                     
                                    $data = getWsData(config('constants.IcConstants.godigit.GODIGIT_BIKE_CREATE_QUOTE_PROPOSAL'),$proposal_data, 'godigit',
                                    [
                                        'enquiryId' => $proposal->user_product_journey_id,
                                        'requestMethod' =>'post',
                                        'productName'  => $productData->product_name,
                                        'company'  => 'godigit',
                                        'method'   => 'Proposal Submit Renewal',
                                        'section' => $productData->product_sub_type_code,
                                        'webUserId' => $webUserId,
                                        'password' => $password,
                                        'transaction_type' => 'proposal',
                                    ]);
                                    
                                    if(!empty($data))
                                    {
                                        set_time_limit(400);
                                        $proposal_response = json_decode($data['response']);


                                        if ($proposal_response->error->errorCode == '0') 
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
                                            $od = 0;
                                            $cng_lpg_tp = 0;
                                            $partsDepreciation=0;
                                            $road_side_assistance=0;
                                            $tppd = 0;
                                            $engine_protection_amt = 0;
                                            $consumables_amt = 0;
                                            $return_to_invoice_amt = 0;
                                            foreach ($contract->coverages as $key => $value) 
                                            {
                                                switch ($key) 
                                                {
                                                    case 'thirdPartyLiability':
                    
                                                        if (isset($value->netPremium))
                                                        {
                                                            $tppd = (str_replace("INR ", "", $value->netPremium));
                                                        }
                                                        
                                                    break;
                                        
                                                    case 'addons':
                                                        if(isset($value->roadSideAssistance) && ($value->roadSideAssistance->selection == 'true'))
                                                        {
                                                            if(isset($value->roadSideAssistance->netPremium))
                                                            {
                                                                $road_side_assistance=(str_replace("INR ", "", $value->roadSideAssistance->netPremium));
                                                            }
                                                        }
                                                        if(isset($value->partsDepreciation) && ($value->partsDepreciation->selection == 'true'))
                                                        {
                                                            if(isset($value->partsDepreciation->netPremium))
                                                            {
                                                                $partsDepreciation=(str_replace("INR ", "", $value->partsDepreciation->netPremium));
                                                            }
                                                        }
                                                        if(isset($value->engineProtection) && ($value->engineProtection->selection == 'true'))
                                                        {
                                                            if(isset($value->engineProtection->netPremium))
                                                            {
                                                                $engine_protection_amt=(str_replace("INR ", "", $value->engineProtection->netPremium));
                                                            }
                                                        }
                                                        if(isset($value->returnToInvoice) && ($value->returnToInvoice->selection == 'true'))
                                                        {
                                                            if(isset($value->returnToInvoice->netPremium))
                                                            {
                                                                $return_to_invoice_amt=(str_replace("INR ", "", $value->returnToInvoice->netPremium));
                                                            }
                                                        }
                                                        if(isset($value->consumables) && ($value->consumables->selection == 'true'))
                                                        {
                                                            if(isset($value->consumables->netPremium))
                                                            {
                                                                $consumables_amt=(str_replace("INR ", "", $value->consumables->netPremium));
                                                            }
                                                        }
                                                    break;
                                
                                                    case 'ownDamage':
                                                        
                                                        if(isset($value->netPremium))
                                                        {
                                                             $od = (str_replace("INR ", "", $value->netPremium));
                                                             foreach ($value->discount->discounts as $key => $type) 
                                                             {
                                                                 if ($type->discountType == "NCB_DISCOUNT") 
                                                                 {
                                                                     $ncb_discount_amt = (str_replace("INR ", "", $type->discountAmount));
                                                                 }
                                                             }
                                                        } 
                                                    break;
                                
                                                    case 'legalLiability' :
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
                                                        if (isset($value->selection) && ($value->selection == 1) && (isset($value->netPremium)))
                                                        {
                                                            $cover_pa_owner_driver_premium = (str_replace("INR ", "", $value->netPremium));
                                                        } 
                                                    break;
                                
                                                    case 'accessories' :
                                                    break;
                                
                                                    case 'unnamedPA':
                                                    
                                                    break;
                                                }
                                            }
                                            if(isset($cng_lpg_amt) && !empty($cng_lpg_amt))
                                            {
                                                $cng_lpg_tp = 60;
                                                $tppd = $tppd - 60;
                                            }
                    
                                            $addon_premium = 0;
                                            $ncb_discount = $ncb_discount_amt;
                                            $final_od_premium = $od;
                                            $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium + $llcleaner_premium  + $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $cover_pa_paid_cleaner_premium + $cover_pa_paid_conductor_premium +$cover_pa_owner_driver_premium;
                                            $addon_premium =$partsDepreciation + $road_side_assistance + $return_to_invoice_amt + $consumables_amt + $engine_protection_amt;
                                            $final_total_discount = $ncb_discount + $voluntary_excess + $ic_vehicle_discount;
                                            $final_net_premium  = (str_replace("INR ", "", $proposal_response->netPremium));
                                            $final_gst_amount = (str_replace("INR ", "", $proposal_response->serviceTax->totalTax)); // 18% IC 
                                            $final_payable_amount = (str_replace("INR ", "", $proposal_response->grossPremium)); 
                
                                            $save = UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                                            ->update([
                                        'od_premium' => $final_od_premium-$final_total_discount,
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
                                        'policy_start_date' => date('d-m-Y',strtotime($policy_start_date)),
                                        'policy_end_date' => $requestData->business_type == 'newbusiness' && $premium_type == 'comprehensive' ? date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date))): ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party' ?  date('d-m-Y', strtotime('+5 year -1 day', strtotime($policy_start_date))): date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)))),
                                        'unique_quote' => $enquiry_id,
                                        'tp_insurance_company' =>!empty($proposal->tp_insurance_company) ? $proposal->tp_insurance_company :'',
                                        'tp_insurance_number' =>!empty($proposal->tp_insurance_number) ? $proposal->tp_insurance_number :'',
                                        'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime($policy_start_date)),
                                        'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+5 year -1 day', strtotime($policy_start_date))) : date('d-m-Y',strtotime($policy_end_date))),
                                        'cpa_start_date' => (($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true' ) ? date('d-m-Y',strtotime($policy_start_date)) :''),
                                        'cpa_end_date'   => $cpa_end_date,
                                        'is_cpa' => ($policy_holder_type == "INDIVIDUAL" && $cpa_selected == 'true') ?'Y' : 'N',
                                        'ic_vehicle_details' => $vehicleDetails,
                                        'is_breakin_case' => $is_breakin_case,
                                        'is_inspection_done'=> $is_breakin_case,
                                ]);
                            updateJourneyStage([
                                'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                                'ic_id' => $productData->company_id,
                                'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                'proposal_id' => $proposal->user_proposal_id,
                            ]);

                            GodigitPremiumDetailController::savePremiumDetails($data['webservice_id']);

                            if(config('constants.IS_CKYC_ENABLED_GODIGIT') === 'Y')
                            {
                                if(config('GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE') === 'Y')
                                {
                                    sleep(10);
                                    if (config('constants.IS_CKYC_ENABLED') == 'Y') 
                                    {
                                        $KycStatusApiResponse = GetKycStatusGoDIgit( $proposal->user_product_journey_id,$proposal_response->policyNumber,  $productData->product_sub_type_name,$proposal->user_proposal_id,customEncrypt( $proposal->user_product_journey_id));
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
        
                                            $message = godigitProposalKycMessage($proposal, $message);
        
                                            if((filter_var($KycStatusApiResponse['message'], FILTER_VALIDATE_URL)))
                                            {
                                                return response()->json([
                                                    'status' => true,
                                                    'msg' => "Proposal Submitted Successfully!",
                                                    'data' => [                            
                                                        'proposalId' => $proposal->user_proposal_id,
                                                        'userProductJourneyId' => $proposal->user_product_journey_id,
                                                        'proposalNo' => $proposal_response->applicationId,
                                                        'finalPayableAmount' => $final_payable_amount, 
                                                        'is_breakin' => $is_breakin_case,
                                                        'kyc_url' => $KycStatusApiResponse['message'],
                                                        'is_kyc_url_present' => true,
                                                        'kyc_message' => $message,
                                                        'kyc_status' => false,
                                                    ]
                                                ]);
                                            }else
                                            {
                                                // if no URL RETURNED BY IC FOR KYC
                                                return response()->json([
                                                    'status' => true,
                                                    'msg' => "Proposal Submitted Successfully!",
                                                    'data' => [                            
                                                        'proposalId' => $proposal->user_proposal_id,
                                                        'userProductJourneyId' => $proposal->user_product_journey_id,
                                                        'proposalNo' => $proposal_response->applicationId,
                                                        'finalPayableAmount' => $final_payable_amount, 
                                                        'is_breakin' => $is_breakin_case,
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
                                            // Need to update the CKYC status for RB
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
                                                'message' => str_replace(",","",$proposal_response->error->validationMessages[0])
                                            ];
                                        } 
                                        elseif(isset($proposal_response->error->errorCode) && $proposal_response->error->errorCode == '400')
                                        {
                                            return 
                                            [
                                                'premium_amount' => 0,
                                                'status' => false,
                                                'message' => str_replace(",","",$proposal_response->error->validationMessages[0])
                                            ];
                                        }
                                    }
                                    else 
                                    {
                                        return
                                        [
                                            'premium_amount' => 0,
                                            'status' => false,
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
                        'message' => str_replace(",","",$response->error->validationMessages[0])
                    ];
                } 
                elseif(isset($response->error->errorCode) && $response->error->errorCode == '400')
                {
                    return 
                    [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => str_replace(",","",$response->error->validationMessages[0])
                    ];
                } else {
                    return [
                        'status' => false,
                        'premium_amount' => 0,
                        'message' => 'Something went wrong'
                    ];
                }
            }
            else 
            {
                return
                [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Insurer not reachable'
                ];
            }       
        }
        else
        {
            return [
                'status' => false,
                'premium' => '0',
                'message' => !empty($response_data->error->validationMessages) ?? 'Insurer not reachable.'
            ];        
        }
    }
}
