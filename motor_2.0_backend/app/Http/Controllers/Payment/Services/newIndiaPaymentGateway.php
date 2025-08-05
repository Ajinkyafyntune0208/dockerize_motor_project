<?php
namespace App\Http\Controllers\Payment\Services;

use App\Models\MasterPolicy;
use App\Models\PaymentRequestResponse;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\AgentIcRelationship;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';
class newIndiaPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $old_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();

        $enquiryId = customDecrypt($request->enquiryId);

        $icId = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first();
        $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->pluck('quote_id')
            ->first();
        $approveproposal = newIndiaPaymentGateway::submit_motor_proposal($enquiryId, $request);
        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();

        if ($approveproposal['status'] == 'true') {
            $reqchecksume = $approveproposal['str'];
        } else {
            return response()->json([
                'status' => false,
                'msg' => $approveproposal['message'],
                'data' => $approveproposal['message'],
            ]);
        }
        $reqchecksume = $approveproposal['str'];
        $checksume = newIndiaPaymentGateway::generate_checksum($reqchecksume);
        $msg = $reqchecksume . '|' . $checksume;
        $return_data = [
            'form_action' => config('constants.IcConstants.new_india.PAYMENT_GATEWAY_LINK_NEW_INDIA'),
            'form_method' => 'POST',
            'payment_type' => 0,
            'form_data' => [
                'msg' => $msg
            ]
        ];
        PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
            ->update(['active' => 0]);

        PaymentRequestResponse::insert([
            'quote_id' => $quote_log_id,
            'ic_id' => $icId,
            'user_product_journey_id' => $enquiryId,
            'user_proposal_id' => $proposal->user_proposal_id,
            'payment_url' => config('constants.IcConstants.new_india.PAYMENT_GATEWAY_LINK_NEW_INDIA'),
            'proposal_no' => $proposal->proposal_no,
            'order_id' => $proposal->proposal_no,
            'amount' => $proposal->final_payable_amount,
            'return_url' => route('car.payment-confirm', ['new_india', 'enquiry_id' => $enquiryId]),
            'status' => STAGE_NAMES['PAYMENT_INITIATED'],
            'active' => 1,
            'xml_data' => json_encode($return_data)
        ]);

        updateJourneyStage([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'ic_id' => $icId,
            'stage' => STAGE_NAMES['PAYMENT_INITIATED']
        ]);
        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);
    }
    public static function submit_motor_proposal($enquiryId, $request)
    {
        $enquiryId = customDecrypt($request['userProductJourneyId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        $quote = DB::table('quote_log')->where('user_product_journey_id', $enquiryId)->first();
        $quote_data = getQuotation(customDecrypt($request['userProductJourneyId']));
        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();

        $cvCategory = '';

        if ($productData->parent_id == 4 || $productData->parent_id == 17) {
            $cvCategory = 'GCV';
        } else if ($productData->parent_id == 8) {
            $cvCategory = 'PCV';
        } else {
            $cvCategory = 'MISC';
        }

        $today_date = date('Y-m-d');
        $insured_name = '';
        if ($requestData->previous_policy_expiry_date != 'New') {
            if (new DateTime($requestData->previous_policy_expiry_date) > new DateTime($today_date)) {
                $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
            } else if (new DateTime($requestData->previous_policy_expiry_date) < new DateTime($today_date)) {
                $policy_start_date = date('d/m/Y', strtotime("+3 day"));
            }
        } else {
            $policy_start_date = date('d/m/Y', strtotime("+1 day"));
        }

        // $mmv = get_mmv_details($productData,$requestData->version_id,'new_india');

        // pcv
        $mmv = array(
            "status" => true,
            "data" => array(
                "SI_No" => "879925",
                "vehicle_model_code" => "1080011",
                "motor_make" => "MARUTI",
                "motor_model" => "DZIRE",
                "motor_cc" => "1197",
                "motor_gvw" => "0",
                "motor_carrying_capacity" => "5",
                "motor_variant" => "1.2 LXI OPTION",
                "motor_product_code" => "CV",
                "motor_fule" => "PETROL",
                "motor_zone" => "MUMBAI",
                "motor_invoice" => "556000",
                "idv_upto_6_months" => "528200",
                "idv_upto_1_year" => "472600",
                "idv_upto_2_years" => "444800",
                "idv_upto_3_years" => "389200",
                "idv_upto_4_years" => "333600",
                "idv_upto_5_years" => "278000",
                "idv_upto_6_years" => "250200",
                "idv_upto_7_years" => "222400",
                "idv_upto_8_years" => "194600",
                "idv_upto_9_years" => "166800",
                "idv_upto_15_years" => "139000",
                "ic_version_code" => "1080011",
                "no_of_wheels" => "0",
            )
        );

        // gcv
        // $mmv = array(
        //     "status" => true,
        //     "data" => array(
        //         "SI_No" => "879925",
        //         "vehicle_model_code" => "1080011",
        //         "motor_make" => "MAHINDRA",
        //         "motor_model" => "BOLERO",
        //         "motor_cc" => "2523",
        //         "motor_gvw" => "2960",
        //         "motor_carrying_capacity" => "3",
        //         "motor_variant" => "PICK UP",
        //         "motor_product_code" => "CV",
        //         "motor_fule" => "Diesel",
        //         "motor_zone" => "MUMBAI",
        //         "motor_invoice" => "721456",
        //         "idv_upto_6_months" => "685384",
        //         "idv_upto_1_year" => "613238",
        //         "idv_upto_2_years" => "577165",
        //         "idv_upto_3_years" => "505020",
        //         "idv_upto_4_years" => "432874",
        //         "idv_upto_5_years" => "360728",
        //         "idv_upto_6_years" => "324656",
        //         "idv_upto_7_years" => "288583",
        //         "idv_upto_8_years" => "252510",
        //         "idv_upto_9_years" => "216437",
        //         "idv_upto_15_years" => "180364",
        //         "ic_version_code" => "1080011",
        //         "no_of_wheels" => "0",
        //     )
        // );

        // misc-d
        // $mmv = array(
        //     "status" => true,
        //     "data" => array(
        //         "SI_No" => "879925",
        //         "vehicle_model_code" => "1080011",
        //         "motor_make" => "PERFECT ENGINEERING WORKS",
        //         "motor_model" => "TRAILER",
        //         "motor_cc" => "1200",
        //         "motor_gvw" => "4000",
        //         "motor_carrying_capacity" => "2",
        //         "motor_variant" => "GVW 4000",
        //         "motor_product_code" => "CV",
        //         "motor_fule" => "DIESEL",
        //         "motor_zone" => "MUMBAI",
        //         "motor_invoice" => "2194661",
        //         "idv_upto_6_months" => "2084927",
        //         "idv_upto_1_year" => "1865461",
        //         "idv_upto_2_years" => "1755726",
        //         "idv_upto_3_years" => "1536260",
        //         "idv_upto_4_years" => "1316794",
        //         "idv_upto_5_years" => "1097328",
        //         "idv_upto_6_years" => "987597",
        //         "idv_upto_7_years" => "877863",
        //         "idv_upto_8_years" => "768132",
        //         "idv_upto_9_years" => "658397",
        //         "idv_upto_15_years" => "548666",
        //         "ic_version_code" => "1080011",
        //         "no_of_wheels" => "12",
        //     )
        // );

        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return [
                'premium_amount' => '0',
                'status' => false,
                'message' => $mmv['message']
            ];
        }
        $veh_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        // POS
        $is_pos = 'No';
        $pos_name = null;
        $pos_name_uiic = null;
        $partyCode = null;
        $partyStakeCode = null;
        $pos_partyCode = null ;
        $pos_partyStakeCode = null ;
        $is_pos_testing_mode = config('IC.NEWINDIA.CV.TESTING_MODE') === 'Y';

        $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type','P')
        ->first();

        if($pos_data && !$is_pos_testing_mode){
            //Properties
            $is_pos = 'YES';
            $pos_name = $pos_data->agent_name;
            $pos_name_uiic = 'uiic';

            //party
            $partyCode = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
            ->pluck('new_india_code')
            ->first();
            $pos_partyStakeCode = $pos_name;
            if(empty($partyCode) || is_null($partyCode))
            {
                return [
                    'status' => false,
                    'premium_amount' => 0,
                    'message' => 'POS details Not Available'
                ];
            }
            $partyStakeCode = 'POS';
        }
        if($is_pos_testing_mode == 'Y')
        {
             //properties
             $is_pos = 'YES';
             $pos_name = 'POS Applicable';
             $pos_name_uiic = 'uiic';
             //properties
             //party
             $partyCode = config('IC.NEWINDIA.CV.POS_PARTY_CODE');//PP00000015
             $partyStakeCode = 'POS';
             //party
        }
        $city_name = DB::table('master_rto')
            ->select('rto_name as city_name')
            ->where('rto_code', $requestData->rto_code)
            ->where('status', 'Active')
            ->first();
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        $reg_no = explode('-', strtoupper($requestData->vehicle_registration_no));
        $Color_as_per_RC_Book = 'As per RC book';
        $age = get_date_diff('month', $requestData->vehicle_register_date);
        $insurer = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();
        $insurer = keysToLower($insurer);
        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'compulsory_personal_accident', 'discounts')
            ->first();
        $anti_theft = 'N';
        $voluntary = 0;
        $legal_liability_to_paid_driver_cover_flag = '0';
        if (!empty($additional['discounts'])) {
            foreach ($additional['discounts'] as $data) {
                if ($data['name'] == 'anti-theft device') {
                    $anti_theft = 'Y';
                }
                if ($data['name'] == 'voluntary_insurer_discounts') {
                    $voluntary = $data['sumInsured'];
                }
            }
        }
        if (!empty($additional['discounts'])) {
            foreach ($additional['discounts'] as $data) {
                if ($data['name'] == 'automobile assiociation') {
                    $automobile_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime(strtr($requestData->automobile_association_expiry_date, '/', '-'))))));
                    $aai_name = 'Automobile Association of Eastern India';
                }
            }
        }

        // if($premium_type == 'own_damage')
        // {
        //     $legal_liability_to_paid_driver_cover_flag = '0';
        // }

        if (($reg_no[0] == 'DL') && (intval($reg_no[1]) < 10)) {
            $car_registration_no = $reg_no[0] . '-0' . $reg_no[1];
        }
        $company_rto_data = DB::table('new_india_rto_master')
            ->where('rto_code', strtr($requestData->rto_code, ['-' => '']))
            ->first();

        if ($requestData->business_type != 'newbusiness') {
            $reg = explode("-", $proposal->vehicale_registration_number);
            $Registration_No_4 = $reg[3];
            if (isset($Registration_No_4) && $Registration_No_4 != '') {
                if (strlen($Registration_No_4) == 3) {
                    $Registration_No_4 = '0' . $Registration_No_4;
                } else if (strlen($Registration_No_4) == 2) {
                    $Registration_No_4 = '00' . $Registration_No_4;
                } else if (strlen($Registration_No_4) == 1) {
                    $Registration_No_4 = '000' . $Registration_No_4;
                } else {
                    $Registration_No_4 = $reg[3];
                }
            }
        }

        if ($requestData->business_type == 'newbusiness') {
            $policy_start_date = Date('d/m/Y');
            $policy_type = "New";
            $reg[0] = 'NEW';
            $reg[1] = '0';
            $reg[2] = 'none';
            $reg[3] = '0001';
            $new_vehicle = 'Y';
            $PreInsurerAdd = '';
            $insured_name = '';
        } else {
            $today_date = date('Y-m-d');
            $insured_name = '';
            if (new DateTime($requestData->previous_policy_expiry_date) > new DateTime($today_date)) {
                $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
            } else if (new DateTime($requestData->previous_policy_expiry_date) < new DateTime($today_date)) {
                $policy_start_date = date('d/m/Y', strtotime("+3 day"));
            } else {
                $policy_start_date = date('d/m/Y', strtotime("+1 day"));
            }
            $PreInsurerAdd = $insurer->address_line_1;
            $PreInsurerAdd2 = $insurer->address_line_2;
            $prev_pincode = $insurer->pin;
            if ($premium_type == 'own_damage') {
                $tp_previous_insurer_address1 = $insurer->address_line_1;
                $tp_previous_insurer_address2 = $insurer->address_line_2;
                $tp_previous_insurer_pin = $insurer->pin;
            } else {
                $tp_previous_insurer_address1 = $tp_previous_insurer_address2 = $tp_previous_insurer_pin = '';
            }
        }
        $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))))));
        $cpa_with_greater_15 = 'Yes';
        $sex = $proposal->gender;
        $cpa_type = 'false';

        if ($quote_data->vehicle_owner_type == 'I') {
            $Gender_of_Nominee = 'NA';
            if (!empty($additional['compulsory_personal_accident'])) {//cpa
                foreach ($additional['compulsory_personal_accident'] as $key => $data) {
                    if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident') {
                        $cpa_with_greater_15 = 'No';
                        $cpa_type = "true";
                    }
                }
            }
        } else {
            $cpa_type = 'false';
            $cpa_with_greater_15 = 'Yes';
            $sex = '';
            $marital_status = '';
            $occupation = '';

            $Name_of_Nominee = $Age_of_Nominee = $Relationship_with_the_Insured = $Gender_of_Nominee = 'NA';
        }
        $birthDate = strtr($proposal->dob, ['-' => '/']);

        $partyType = ($quote_data->vehicle_owner_type == 'I') ? 'I' : 'O';
        if ($partyType == 'I') {
            $policy_holder_array = [
                'ns1:userCode' => 'USRPB',
                'ns1:rolecode' => 'SUPERUSER',#'SUPERUSER',
                'ns1:PRetCode' => '1',
                'ns1:userId' => '',
                'ns1:stakeCode' => 'POLICY-HOL',
                'ns1:roleId' => '',
                'ns1:userroleId' => '',
                'ns1:branchcode' => '',
                'ns1:PRetErr' => '',
                'ns1:startDate' => $policy_start_date,
                'ns1:stakeName' => '',
                'ns1:title' => '',
                'ns1:typeOfOrg' => '',
                'ns1:address' => $proposal->address_line1,
                'ns1:firstName' => $proposal->first_name,
                'ns1:partyCode' => '',
                'ns1:company' => '',
                'ns1:sex' => $sex,
                'ns1:address2' => $proposal->address_line2,
                'ns1:EMailid2' => '',
                'ns1:partyStakeCode' => 'POLICY-HOL',
                'ns1:partyType' => $partyType,
                'ns1:regNo' => '',
                'ns1:midName' => '',
                'ns1:city' => $proposal->city,
                'ns1:phNo3' => $proposal->mobile_number,
                'ns1:regDate' => '',
                'ns1:contactType' => 'Permanent',
                'ns1:businessName' => '',
                'ns1:status' => '',
                'ns1:EMailid1' => $proposal->email,
                'ns1:clientType' => '',
                'ns1:birthDate' => $birthDate,
                'ns1:lastName' => $proposal->last_name,
                'ns1:sector' => 'NP',
                'ns1:country' => '',
                'ns1:pinCode' => $proposal->pincode,
                'ns1:prospectId' => '',
                'ns1:state' => $proposal->state_id,
                'ns1:address3' => $proposal->address_line3 . ' ' . $proposal->city,
                'ns1:phNo1' => '',
                'ns1:businessAddress' => '',
                'ns1:phNo2' => '',
                'ns1:partyName' => '',
                'ns1:panNo' => !is_null($proposal->pan_number) ? $proposal->pan_number : '',
                'ns1:gstRegIdType' => '',
                'ns1:gstin' => !is_null($proposal->gst_number) ? $proposal->gst_number : '',
            ];
        } else {
            $policy_holder_array = [
                'typ:userCode' => config('constants.IcConstants.new_india.USERCODE_NEW_INDIA'),
                'typ:rolecode' => config('constants.IcConstants.new_india.ROLE_CODE_NEW_INDIA'),#'SUPERUSER',
                'typ:PRetCode' => '0',
                'typ:userId' => '',
                'typ:stakeCode' => 'BROKER',
                'typ:roleId' => '',
                'typ:userroleId' => '',
                'typ:branchcode' => '',
                'typ:PRetErr' => '',
                'typ:startDate' => $policy_start_date,
                'typ:stakeName' => '',
                'typ:title' => '',
                'typ:typeOfOrg' => '',
                'typ:address' => $proposal->address_line1,
                'typ:firstName' => '',
                'typ:partyCode' => '',
                'typ:company' => '',
                'typ:sex' => $sex,
                'typ:address2' => $proposal->address_line2,
                'typ:EMailid2' => '',
                'typ:partyStakeCode' => 'FINANCIER',
                'typ:partyType' => $partyType,
                'typ:regNo' => '',
                'typ:midName' => '',
                'typ:city' => $proposal->city,
                'typ:phNo3' => $proposal->mobile_number,
                'typ:regDate' => '',
                'typ:contactType' => 'Permanent',
                'typ:businessName' => $requestData->business_type,
                'typ:status' => '',
                'typ:EMailid1' => $proposal->email,
                'typ:clientType' => ($proposal->financer_agreement_type != '') ? 'HYPO' : 'NA',
                'typ:birthDate' => '01/01/2000',//$birthDate,
                'typ:lastName' => '',
                'typ:sector' => 'NP',
                'typ:country' => '',
                'typ:pinCode' => $proposal->pincode,
                'typ:prospectId' => '',
                'typ:state' => $proposal->state_id,
                'typ:address3' => $proposal->address_line3 . ' ' . $proposal->city,
                'typ:phNo1' => '',
                'typ:businessAddress' => '',
                'typ:phNo2' => '',
                'typ:partyName' => '',
                'typ:panNo' => !is_null($proposal->pan_number) ? $proposal->pan_number : '',
                'typ:gstRegIdType' => 'NCC',
                'typ:gstin' => !is_null($proposal->gst_number) ? $proposal->gst_number : '',
            ];
        }
        $cost_of_consumable = 'No';
        $engine_protector_cover = 'No';
        $return_to_invoice_cover = 'No';
        $rsa_cover = 'No';
        $key_replacement_cover = 'No';
        $tyre_secure_cover = 'No';
        $loss_of_personal_belongings_cover = 'No';
        $ncb_protector_cover = 'No';
        $no_of_unnamed_persons = '0';
        $no_of_paid_drivers = '0';
        $no_of_paid_conductors = '0';
        $no_of_paid_cleaners = '0';
        $cover_pa_paid_driver = '0';
        $include_pa_cover_for_paid_driver = 'N';
        $cover_pa_unnamed_passenger = '0';
        $capital_si_for_unnamed_persons = '0';
        $individual_si_for_unnamed_person = '0';
        $include_pa_cover_for_unnamed_person = 'N';
        $lpg_cng_kit = '0.00';
        $bi_fuel_type = 'CNG';
        $extra_electrical_electronic_fittings = 'N';
        $extra_non_electrical_electronic_fittings = 'N';
        $total_value_of_electrical_electronic_fittings = '0';
        $total_value_of_non_electrical_electronic_fittings = '0';
        $type_of_fuel = $veh_data->motor_fule;
        $si_for_paid_drivers = '0';
        $own_premises_limited = 'N';
        $tppd_flag = 'N';
        $tppd_amount = '0';
        $ll_no_driver = '0';
        $ll_no_cleaner = '0';
        $ll_no_conductor = '0';

        $srilanka = 0;
        $pak = 0;
        $bang = 0;
        $bhutan = 0;
        $nepal = 0;
        $maldive = 0;
        $is_geo_ext = false;

        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as $key => $data) {
                if ($data['name'] == 'PA paid driver/conductor/cleaner' && isset($data['sumInsured'])) {
                    $cover_pa_paid_driver = $data['sumInsured'];
                    $no_of_paid_drivers = '1';
                    $no_of_paid_conductors = '1';
                    $no_of_paid_cleaners = '1';
                    $include_pa_cover_for_paid_driver = 'Y';
                }

                if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                    $cover_pa_unnamed_passenger = $data['sumInsured'];
                    $include_pa_cover_for_unnamed_person = 'Y';
                    $no_of_unnamed_persons = $veh_data->motor_carrying_capacity;
                    $capital_si_for_unnamed_persons = ($veh_data->motor_carrying_capacity) * $data['sumInsured'];
                }

                if ($data['name'] == 'Geographical Extension' && $productData->zero_dep != 0) {
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

        if (!empty($additional['addons'])) {
            foreach ($additional['addons'] as $key => $data) {
                if ($data['name'] == 'Road Side Assistance' && $age <= 58) {
                    $rsa_cover = 'Yes';
                }

                if ($data['name'] == 'Tyre Secure' && $age <= 34) {
                    $tyre_secure_cover = 'Yes';
                }

                if ($data['name'] == 'Engine Protector' && $age <= 58) {
                    $engine_protector_cover = 'Yes';
                }

                if ($data['name'] == 'NCB Protection' && $age <= 58 && $requestData->is_claim == 'N') {
                    $ncb_protector_cover = 'Yes';
                }

                if ($data['name'] == 'Return To Invoice' && $age <= 34) {
                    $return_to_invoice_cover = 'Yes';
                }

                if ($data['name'] == 'Consumable' && $age <= 58) {
                    $cost_of_consumable = 'Yes';
                }
                if ($data['name'] == 'Loss of Personal Belongings' && $age <= 58) {
                    $loss_of_personal_belongings_cover = 'Yes';
                }

                if ($data['name'] == 'Key Replacement' && $age <= 58) {
                    $key_replacement_cover = 'Yes';
                }
            }
        }
        $llpd_flag = 'N';
        $ll_no_driver = 0;
        $ll_no_conductor = 0;
        $ll_no_cleaner = 0;
        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as $data) {
                if ($data['name'] == 'LL paid driver/conductor/cleaner') {
                    $llpd_flag = 'Y';

                    in_array("DriverLL", $data['selectedLLpaidItmes']) ? $ll_no_driver = $data['LLNumberDriver'] : '0';
                    in_array("ConductorLL", $data['selectedLLpaidItmes']) ? $ll_no_conductor = $data['LLNumberConductor'] : '0';
                    in_array("CleanerLL", $data['selectedLLpaidItmes']) ? $ll_no_cleaner = $data['LLNumberCleaner'] : '0';
                }

                if ($data['name'] == 'PA cover for additional paid driver') {
                    $cover_pa_paid_driver = $data['sumInsured'];
                    $no_of_paid_drivers = '1';
                    $include_pa_cover_for_paid_driver = 'Y';
                }

                if ($data['name'] == 'LL paid driver') {
                    $llpd_flag = 'Y';
                    $ll_no_driver = '1';
                }
            }
        }

        if ($requestData->previous_ncb !== '' && $requestData->is_claim == 'N' && $premium_type != 'third_party') {
            $no_claim_bonus = $requestData->applicable_ncb;
        } else {
            $no_claim_bonus = '0';
        }
        if ($requestData->business_type == 'newbusiness') {
            $Previous_Policy_Number = '0';
        }
        if (!empty($additional['accessories'])) {
            foreach ($additional['accessories'] as $key => $data) {
                if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                    $lpg_cng_kit = $data['sumInsured'];
                    $type_of_fuel = 'CNG';#$bi_fuel_type .' '.$veh_data->motor_fule;
                }

                if ($data['name'] == 'Non-Electrical Accessories') {
                    $extra_non_electrical_electronic_fittings = 'Y';
                    $total_value_of_non_electrical_electronic_fittings = $data['sumInsured'];
                }

                if ($data['name'] == 'Electrical Accessories') {
                    $extra_electrical_electronic_fittings = 'Y';
                    $total_value_of_electrical_electronic_fittings = $data['sumInsured'];
                }
            }
        }

        if (!empty($additional['discounts'])) {
            foreach ($additional['discounts'] as $key => $data) {
                if ($data['name'] == 'Vehicle Limited to Own Premises') {
                    $own_premises_limited = 'Y';
                }

                if ($data['name'] == 'TPPD Cover') {
                    $tppd_flag = 'Y';
                    $tppd_amount = '6000';
                }
            }
        }

        $zone_cities = [
            'ahmedabad',
            'bangalore',
            'bengaluru',
            'chennai',
            'delhi',
            'hyderabad',
            'kolkata',
            'mumbai',
            'new delhi',
            'pune'
        ];

        $vehicle_zone = (in_array(strtolower($city_name->city_name), $zone_cities)) ? 'A' : 'B';
        $registration_authority = $company_rto_data->rta_name . ' ' . $company_rto_data->rta_address;

        unset($city_name->city_name, $zone_cities, $requestData->rto_code, $company_rto_data);
        $total_accessories_idv = $total_value_of_electrical_electronic_fittings + $total_value_of_non_electrical_electronic_fittings + $lpg_cng_kit;
        $idv = $quote->idv;//- $total_accessories_idv;
        $ex_showroom_price = $veh_data->motor_invoice;
        $insured_name = $proposal->previous_insurance_company;
        if (trim($insured_name) == '') {
            $insured_name = 'NA';
        }
        if (trim($PreInsurerAdd) == '') {
            $PreInsurerAdd = 'NA';
        }

        switch ($premium_type) {
            case 'comprehensive':
                $coverCode = 'PACKAGE';
                $product_id = '194731914122007';
                $product_code = 'CV';
                $product_name = 'Commercial Vehicle';
                break;
            case 'third_party':
                $coverCode = 'LIABILITY';
                $product_id = '194398713122007';
                $product_code = 'CV';
                $product_name = 'Commercial Vehicle';
                break;
            case 'own_damage':
                $coverCode = 'ODWTOTADON'; //'ODWTHADDON';
                $product_id = '120087123072019';
                $product_code = 'SS';
                $product_name = 'Standalone OD policy for PC';
                break;
        }

        $covers = [
            'typ:productCode' => '',
            'typ:policyDetailid' => '',
            'typ:coverCode' => ($productData->zero_dep == 1) ? $coverCode : (($premium_type == 'own_damage') ? 'ODWTHADDON' : 'EHMNTCOVER'),
            'typ:productId' => '',
            'typ:coverExpiryDate' => '',
            'typ:properties' => [
                [
                    'typ:value' => $tppd_flag,
                    'typ:name' => 'Do You want to reduce TPPD cover to the statutory limit of Rs.6000',
                ],
                [
                    'typ:value' => $tppd_amount,
                    'typ:name' => 'Sum Insured for TPPD',
                ],
                [
                    'typ:value' => '',
                    'typ:name' => 'Sum Insured for PA cover',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name' => 'LL under WCA,for carriage of more than six employees(excluding the Driver)',
                ],
                [
                    'typ:value' => '0',
                    'typ:name' => 'Number of Additional LL Employees',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name' => 'LL to persons employed for opn and/or maint.and/or loading and/or unloading',
                ],
                [
                    'typ:value' => '0',
                    'typ:name' => 'Number Of LL Employees',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name' => 'LL to Non-fare Paying Passengers,Owner of goods(Not Employees of the Insured)',
                ],
                [
                    'typ:value' => '0',
                    'typ:name' => 'Number of LL Non fare Paying Passengers(Excluding Employee)',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name' => 'LL to Non-fare Paying Passengers (Employee of Insurd but not Workmen under WCA)',
                ],
                [
                    'typ:value' => '0',
                    'typ:name' => 'Number of LL Non fare Paying Passengers(Including Employee)',
                ],
                [
                    'typ:value' => $include_pa_cover_for_paid_driver,
                    'typ:name' => 'Do you wish to include PA Cover for Paid Drivers, Cleaner, Conductor',
                ],
                [
                    'typ:value' => $no_of_paid_drivers,
                    'typ:name' => 'No of Paid Drivers',
                ],
                [
                    'typ:value' => $no_of_paid_cleaners,
                    'typ:name' => 'No of Cleaners',
                ],
                [
                    'typ:value' => $no_of_paid_conductors,
                    'typ:name' => 'No of Conductors',
                ],
                [
                    'typ:value' => (string) $cover_pa_paid_driver,
                    'typ:name' => 'Capital SI for Driver,Cleaner,Conductor per Person',
                ],
                [
                    'typ:value' => $include_pa_cover_for_unnamed_person,
                    'typ:name' => 'Do you want to include PA cover for unnamed person',
                ],
                [
                    'typ:value' => $no_of_unnamed_persons,
                    'typ:name' => 'No of unnamed Persons',
                ],
                [
                    'typ:value' => $capital_si_for_unnamed_persons,
                    'typ:name' => 'Capital SI for unnamed Persons',
                ],
                [
                    'typ:value' => $llpd_flag,
                    'typ:name' => 'LL to paid driver and/or conductor and/or cleaner employed for operation',
                ],
                [
                    'typ:value' => $ll_no_driver,
                    'typ:name' => 'Number of LL paid driver',
                ],
                [
                    'typ:value' => $ll_no_conductor,
                    'typ:name' => 'Number of LL conductor',
                ],
                [
                    'typ:value' => $ll_no_cleaner,
                    'typ:name' => 'Number of LL cleaner',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name' => 'LL to Non-fare Paying Passengers (Not Employees of the Insured and not Workmen under WCA)',
                ],
                [
                    'typ:value' => '0',
                    'typ:name' => 'Number of LL Non-fare Paying Passengers(Not Employees)',
                ],
                [
                    'typ:value' => '0',
                    'typ:name' => 'Capital SI for Drivers',
                ],
                [
                    'typ:value' => 'A',
                    'typ:name' => 'Type of Liability Coverage',
                ],
                [
                    'typ:value' => $cvCategory == 'MISC' ? 'A' : 'B',
                    'typ:name' => 'Type of Enhancement cover',
                ],

                // addons 
                [
                    'typ:value' => $engine_protector_cover,
                    'typ:name' => 'Engine protect cover',
                ],
                [
                    'typ:value' => $cost_of_consumable,
                    'typ:name' => 'Consumable Items Cover',
                ],
                [
                    'typ:value' => $tyre_secure_cover,
                    'typ:name' => 'Tyre and Alloy Cover',
                ],
                [
                    'typ:value' => $key_replacement_cover,
                    'typ:name' => 'Key Protect Cover',
                ],
                [
                    'typ:value' => $loss_of_personal_belongings_cover,
                    'typ:name' => 'Personal Belongings Cover',
                ],
                [
                    'typ:value' => $rsa_cover,
                    'typ:name' => 'Additional towing charges cover',
                ],
                [
                    'typ:value' => ($rsa_cover == 'Yes') ? '10000' : '0',
                    'typ:name' => 'Additional towing charges Amount',
                ],
                [
                    'typ:value' => $ncb_protector_cover,
                    'typ:name' => 'NCB Protection Cover',
                ],
                [
                    'typ:value' => $return_to_invoice_cover,
                    'typ:name' => 'Return to invoice cover',
                ],
                [
                    'typ:value' => ($return_to_invoice_cover == 'Yes') ? $ex_showroom_price : '0',
                    'typ:name' => 'Total Ex-Showroom Price',
                ],
                [
                    'typ:value' => ($return_to_invoice_cover == 'Yes') ? '1' : '0',
                    'typ:name' => 'First Year Insurance Premium',
                ],
                [
                    'typ:value' => ($return_to_invoice_cover == 'Yes') ? '20' : '0',
                    'typ:name' => 'Registration Charges',
                ],
            ],
        ];

        $covers = trim_array($covers);
        $first_reg_date = strtr($requestData->vehicle_register_date, ['-' => '/']);
        $existing_policy_expiry_date = strtr($requestData->previous_policy_expiry_date, ['-' => '/']);
        $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
        $manf_year = $motor_manf_year_arr[1];

        $properties = [
            [
                'typ:value' => $is_pos,
                'typ:name'  => $pos_name,
            ],
            [
                'typ:value' => 'SKIInsurance',
                'typ:name' => 'channelcode',
            ],
            [
                'typ:value' => 'Y',
                'typ:name' => 'Fire explosion self ignition or lightning peril required',
            ],
            [
                'typ:value' => 'Y',
                'typ:name' => 'Burglary housebreaking or theft peril required',
            ],
            [
                'typ:value' => 'Y',
                'typ:name' => 'Riot and strike peril required',
            ],
            [
                'typ:value' => 'Y',
                'typ:name' => 'Earthquake damage peril required',
            ],
            [
                'typ:value' => 'Y',
                'typ:name' => 'Flood typhoon hurricane storm tempest inundation cyclone hailstorm frost peril required',
            ],
            [
                'typ:value' => 'Y',
                'typ:name' => 'Accidental external means peril required',
            ],
            [
                'typ:value' => 'Y',
                'typ:name' => 'Malicious act peril required',
            ],
            [
                'typ:value' => 'Y',
                'typ:name' => 'Terrorist activity peril required',
            ],
            [
                'typ:value' => 'Y',
                'typ:name' => 'Whilst in transit by road rail inland-waterway lift elevator or air peril required',
            ],
            [
                'typ:value' => 'Y',
                'typ:name' => 'Landslide rockslide peril required',
            ],
            [
                'typ:value' => 'N',
                'typ:name' => 'Is it declaration type policy',
            ],
            [
                'typ:value' => 'N',
                'typ:name' => 'Is Service Tax Exempted',
            ],
            [
                'typ:value' => 'NO',
                'typ:name' => 'Co-Insurance Applicable',
            ],
            [
                'typ:value' => '0',
                'typ:name' => 'Individual agent Commission for OD',
            ],
            [
                'typ:value' => '0',
                'typ:name' => 'Corporate Agent Commission for OD',
            ],
            [
                'typ:value' => 'N',
                'typ:name' => 'Is Business Sourced from Tie Up',
            ],
            [
                'typ:value' => 'Non-Dealer',
                'typ:name' => 'Auto Tie Up Type',
            ],
            [
                'typ:value' => '0',
                'typ:name' => 'Broker Commission for OD',
            ],
        ];

        $risk_properties = [
            [
                'typ:value' => $is_pos,
                'typ:name'  => $pos_name_uiic,
            ],
            [
                'typ:value' => $requestData->vehicle_register_date, //$first_reg_date
                'typ:name' => 'Date of Purchase of Vehicle by Proposer',
            ],
            [
                'typ:value' => 'New',
                'typ:name' => 'Vehicle New or Second hand at the time of purchase',
            ],
            [
                'typ:value' => 'N',
                'typ:name' => 'Vehicle Used for Private,Social,domestic,pleasure,professional purpose',
            ],
            [
                'typ:value' => 'Y',
                'typ:name' => 'Is vehicle in good condition',
            ],
            [
                'typ:value' => 'NA',
                'typ:name' => 'Give Vehicle Details',
            ],
            [
                'typ:value' => 'NO',
                'typ:name' => 'Whether the Vehicle is Government Vehicle',
            ],
            [
                'typ:value' => $cvCategory == 'GCV' ? 'A' : ($cvCategory == 'MISC' ? 'D' : 'C'),
                'typ:name' => 'Type of Commercial Vehicles',
            ],
            [
                'typ:value' => $cvCategory == 'GCV' ? 'OTH-PBC' : 'NA',
                'typ:name' => 'Type of Goods Carrying',
            ],
            [
                'typ:value' => 'NA',
                'typ:name' => 'Goods Carrying vehicle description',
            ],
            [
                'typ:value' => $cvCategory == 'GCV' ? 'C' : 'NA',
                'typ:name' => 'Type of Body ( Goods carrying )',
            ],
            [
                'typ:value' => $cvCategory == 'PCV' ? '4WH-' . $veh_data->motor_carrying_capacity : 'NA',
                'typ:name' => 'Type of Passenger Carrying',
            ],
            [
                'typ:value' => $cvCategory == 'PCV' ? 'C' : 'NA',
                'typ:name' => 'Type of Body ( Passenger Carrying )',
            ],
            [
                'typ:value' => $cvCategory == 'MISC' ? 'AMB' : 'NA',
                'typ:name' => 'Type of Misc & Special Type',
            ],
            [
                'typ:value' => $cvCategory == 'MISC' ? 'C' : 'NA',
                'typ:name' => 'Type of Body ( Misc & Special Type )',
            ],
            [
                'typ:value' => $cvCategory == 'MISC' ? 'OTHERS' : 'NA',
                'typ:name' => 'Type of Body ( Others )',
            ],
            [
                'typ:value' => 'NA',
                'typ:name' => 'Type of Road Risk',
            ],
            [
                'typ:value' => 'N',
                'typ:name' => 'Is Vehicle AC?',
            ],
            [
                'typ:value' => $proposal->vehicle_color,
                'typ:name' => 'Color of Vehicle',
            ],
            [
                'typ:value' => $Color_as_per_RC_Book,
                'typ:name' => 'Color as per RC book',
            ],
            [
                'typ:value' => $vehicle_zone,
                'typ:name' => 'Vehicle Zone for CV',
            ],
            [
                'typ:value' => 'NA',
                'typ:name' => 'Vehicle zone for CV(C1-C4)',
            ],
            [
                'typ:value' => $new_vehicle,
                'typ:name' => 'New Vehicle',
            ],
            [
                'typ:value' => $manf_year,
                'typ:name' => "Year of Manufacture"
            ],
            [
                'typ:value' => $requestData->vehicle_register_date,
                'typ:name' => "Date of Sale"
            ],
            [
                'typ:value' => $reg[0],
                'typ:name' => "Registration No (1)"
            ],
            [
                'typ:value' => $reg[1],
                'typ:name' => "Registration No (2)"
            ],
            [
                'typ:value' => $reg[2],
                'typ:name' => "Registration No (3)"
            ],
            [
                'typ:value' => $Registration_No_4 ?? $reg[3],
                'typ:name' => "Registration No (4)"
            ],
            [
                'typ:value' => $requestData->vehicle_register_date,
                'typ:name' => "Registration Date"
            ],
            [
                'typ:value' => "31/12/2030",
                'typ:name' => "Registration Validity Date"
            ],
            [
                'typ:value' => $cvCategory == 'PCV' ? 'PUB' : 'NA',
                'typ:name' => "Purpose of Using Passenger Vehicle(C1)"
            ],
            [
                'typ:value' => "PUB",
                'typ:name' => "Purpose of Using Passenger Vehicle(C2,C3,C4)"
            ],
            [
                'typ:value' => "NA",
                'typ:name' => "Other Purpose of Using Vehicle"
            ],
            [
                'typ:value' => "Y",
                'typ:name' => "Vehicle in roadworthy condition and free from damage"
            ],
            [
                'typ:value' => "0",
                'typ:name' => "Loading amount for not Roadworthy Condition"
            ],
            [
                'typ:value' => ($premium_type == 'third_party') ? '0' : $ex_showroom_price,
                'typ:name' => "Vehicle Invoice Value"
            ],
            [
                'typ:value' => $age,
                'typ:name' => "Vehicle Age"
            ],
            [
                'typ:value' => ($premium_type == 'third_party') ? '0' : $idv,
                'typ:name' => "Insureds declared Value (IDV)"
            ],
            [
                'typ:value' => "Yes",
                'typ:name' => "Vehicle Used for Carriage of Own Goods (IMT-42)"
            ],
            [
                'typ:value' => $requestData->business_type == 'newbusiness' ? 'none' : $registration_authority,
                'typ:name' => "Name and Address of Registration Authority"
            ],
            [
                'typ:value' => $proposal->engine_number,
                'typ:name' => "(*)Engine No"
            ],
            [
                'typ:value' => $proposal->chassis_number,
                'typ:name' => "(*)Chassis No"
            ],
            [
                'typ:value' => substr($veh_data->motor_make, 0, 10),
                'typ:name' => "Make"
            ],
            [
                'typ:value' => $veh_data->motor_model,
                'typ:name' => "Model"
            ],
            [
                'typ:value' => $veh_data->motor_variant,
                'typ:name' => "Variant"
            ],
            [
                'typ:value' => "NA",
                'typ:name' => "Transit From"
            ],
            [
                'typ:value' => "NA",
                'typ:name' => "Transit To"
            ],
            [
                'typ:value' => "0",
                'typ:name' => "Distance Covered"
            ],
            [
                'typ:value' => $extra_electrical_electronic_fittings,
                'typ:name' => "Extra Electrical/ Electronic fittings"
            ],
            [
                'typ:value' => "0",
                'typ:name' => "Value of Music System"
            ],
            [
                'typ:value' => "0.00",
                'typ:name' => "Value of AC/Fan"
            ],
            [
                'typ:value' => "0.00",
                'typ:name' => "Value of Lights"
            ],
            [
                'typ:value' => "0", // $total_value_of_electrical_electronic_fittings,
                'typ:name' => "Value of Other Fittings"
            ],
            [
                'typ:value' => $total_value_of_electrical_electronic_fittings,
                'typ:name' => "Total Value of Extra Electrical/ Electronic fittings"
            ],
            [
                'typ:value' => $extra_non_electrical_electronic_fittings,
                'typ:name' => "Non-Electrical/ Electronic fittings"
            ],
            [
                'typ:value' => $total_value_of_non_electrical_electronic_fittings,
                'typ:name' => "Value of Non- Electrical/ Electronic fittings"
            ],
            [
                'typ:value' => $type_of_fuel,
                'typ:name' => "Type of Fuel"
            ],
            [
                'typ:value' => in_array($type_of_fuel, ['CNG', 'CNGPetrol', 'LPG']) ? 'Y' : 'N',
                'typ:name' => "In Built Bi-fuel System fitted"
            ],
            [
                'typ:value' => $lpg_cng_kit,
                'typ:name' => "Bi-fuel System Value"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Fibre glass fuel tanks"
            ],
            [
                'typ:value' => "0",
                'typ:name' => "Value of Fibre glass fuel tanks"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Whether it is a Two wheeler"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Is side car attached with a two wheeler"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Indemnity to Hirers"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Additional Towing Coverage Required"
            ],
            [
                'typ:value' => "0",
                'typ:name' => "Additional Towing Coverage Amount"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Is IMT 23 to be deleted"
            ],
            [
                'typ:value' => "0",
                'typ:name' => "Number of Trailers"
            ],
            [
                'typ:value' => "NA",
                'typ:name' => "Type of Trailer"
            ],
            [
                'typ:value' => ($premium_type == 'third_party') ? '0' : $idv + $total_accessories_idv,
                'typ:name' => "Total IDV"
            ],
            [
                'typ:value' => $veh_data->motor_carrying_capacity,
                'typ:name' => "(*)Seating Capacity"
            ],
            [
                'typ:value' => $veh_data->motor_carrying_capacity,
                'typ:name' => "(*)Carrying Capacity"
            ],
            [
                'typ:value' => $veh_data->motor_cc,
                'typ:name' => "(*)Cubic Capacity"
            ],
            [
                'typ:value' => $veh_data->motor_gvw,
                'typ:name' => "(*)Gross Vehicle Weight(GVW)"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Whether vehicle is used for driving tuition"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Whether vehicle belongs to foreign embassy or consulate"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Whether vehicle belongs to foreign embassys"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Whether vehicle designed/modified for visually impaired /physically challenged"
            ],
            [
                'typ:value' => ($anti_theft == 'Y') ? 'Y' : 'N',
                'typ:name' => "Is the vehicle fitted with Anti-theft device"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Whether Vehicle designed as commercial vehicle and used for commercial and private purpose"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Vehicle use is limited to own premises"
            ],
            [
                'typ:value' => ($premium_type == 'third_party') ? '0' : $no_claim_bonus,
                'typ:name' => "NCB Applicable Percentage"
            ],
            [
                'typ:value' => $insured_name,
                'typ:name' => "Name of Previous Insurer"
            ],
            [
                'typ:value' => $proposal->previous_policy_number,
                'typ:name' => "Previous Policy No"
            ],
            [
                'typ:value' => $PreInsurerAdd,
                'typ:name' => "Address of the Previous Insurer"
            ],
            [
                'typ:value' => ($requestData->business_type == 'newbusiness' ? '01/01/0001' : strtr($requestData->previous_policy_expiry_date, ['-' => '/'])),
                'typ:name' => "Expiry date of previous Policy"
            ],
            [
                'typ:value' => "GOODS",
                'typ:name' => "Vehicle Permit Details"
            ],
            [
                'typ:value' => "Y",
                'typ:name' => "Do You Hold Valid Driving License"
            ],
            [
                'typ:value' => $cvCategory == 'PCV' ? 'MPMOTORVEH' : 'MGOODSVEH',
                'typ:name' => "License Type of Owner Driver"
            ],
            [
                'typ:value' => "0",
                'typ:name' => "Age of Owner Driver"
            ],
            [
                'typ:value' => "none",
                'typ:name' => "Owner Driver Driving License No"
            ],
            [
                'typ:value' => "",
                'typ:name' => "Owner Driver License Issue Date"
            ],
            [
                'typ:value' => "01/01/0001",
                'typ:name' => "Owner Driver License Expiry Date"
            ],
            [
                'typ:value' => "none",
                'typ:name' => "License Issuing Authority for Owner Driver"
            ],
            [
                'typ:value' => !empty($proposal->nominee_name) ? $proposal->nominee_name : 'NA',
                'typ:name' => "Name of Nominee"
            ],
            [
                'typ:value' => !empty($proposal->nominee_age) ? $proposal->nominee_age : 'NA',
                'typ:name' => "Age of Nominee"
            ],
            [
                'typ:value' => !empty($proposal->nominee_relationship) ? $proposal->nominee_relationship : 'NA',
                'typ:name' => "Relationship with the Insured"
            ],
            [
                'typ:value' => $Gender_of_Nominee,
                'typ:name' => "Gender of the Nominee"
            ],
            [
                'typ:value' => "NA",
                'typ:name' => "Name of the Appointee (if Nominee is a minor)"
            ],
            [
                'typ:value' => "NA",
                'typ:name' => "Relationship to the Nominee"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Any of the driver ever convicted or any prosecution pending"
            ],
            [
                'typ:value' => "",
                'typ:name' => "Details of Conviction"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "In last 3 years any driver involved in any accident or loss"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Do you Have Any other Driver"
            ],
            [
                'typ:value' => "OTHERS",
                'typ:name' => "Driver Type"
            ],
            [
                'typ:value' => "",
                'typ:name' => "Driver Name"
            ],
            [
                'typ:value' => ($cpa_type == 'true') ?'Y':'N',
                'typ:name' => "Do you hold a valid license No."
            ],
            [
                'typ:value' => "01/01/0001",
                'typ:name' => "Issue Date"
            ],
            [
                'typ:value' => "01/01/0001",
                'typ:name' => "Date of birth"
            ],
            [
                'typ:value' => $sex,
                'typ:name' => "Sex"
            ],
            [
                'typ:value' => "NA",
                'typ:name' => "Address"
            ],
            [
                'typ:value' => "",
                'typ:name' => "License Number"
            ],
            [
                'typ:value' => "01/01/0001",
                'typ:name' => "Expiry Date"
            ],
            [
                'typ:value' => "",
                'typ:name' => "Age"
            ],
            [
                'typ:value' => "",
                'typ:name' => "Experience"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Whether eligible for special discount"
            ],
            [
                'typ:value' => "",
                'typ:name' => "Loading amount for OD"
            ],
            [
                'typ:value' => "",
                'typ:name' => "Loading amount for TP"
            ],
            [
                'typ:value' => ($is_geo_ext == 1) ? 'Y' : 'N',
                'typ:name' => "Extension of Geographical Area required"
            ],
            [
                'typ:value' => $bang == 1 ? 'Y' : 'N',
                'typ:name' => "Extension of Geographical Area to Bangladesh"
            ],
            [
                'typ:value' => $bhutan == 1 ? 'Y' : 'N',
                'typ:name' => "Extension of Geographical Area to Bhutan"
            ],
            [
                'typ:value' => $nepal == 1 ? 'Y' : 'N',
                'typ:name' => "Extension of Geographical Area to Nepal"
            ],
            [
                'typ:value' => $pak == 1 ? 'Y' : 'N',
                'typ:name' => "Extension of Geographical Area to Pakistan"
            ],
            [
                'typ:value' => $srilanka == 1 ? 'Y' : 'N',
                'typ:name' => "Extension of Geographical Area to Sri Lanka"
            ],
            [
                'typ:value' => $maldive == 1 ? 'Y' : 'N',
                'typ:name' => "Extension of Geographical Area to Maldives"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Vehicle Requisitioned by Government"
            ],
            [
                'typ:value' => "0",
                'typ:name' => "Policy Excess (Rs)"
            ],
            [
                'typ:value' => "",
                'typ:name' => "Imposed Excess (Rs)"
            ],
            [
                'typ:value' => ($premium_type == 'third_party') ? '0' : '0',
                'typ:name' => "OD discount (%)"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Commercial Vehicle Type G"
            ],
            [
                'typ:value' => "N",
                'typ:name' => "Commercial Vehicle Type G or F"
            ],
            [
                'typ:value' => "0",
                'typ:name' => "Additional Loading for vehicles"
            ]
        ];

        // if ($requestData->business_type != 'newbusiness' || $premium_type == 'third_party') {
        //     $tp_details = [
        //         [
        //             'typ:value' => ($premium_type == 'own_damage') ? $proposal->tp_insurance_company : '',
        //             'typ:name' => 'Name (Bundled/Long Term Liability policy Insurer)',
        //         ],
        //         [
        //             'typ:value' => ($premium_type == 'own_damage') ? $proposal->tp_insurance_number : '',
        //             'typ:name' => 'Bundled/Long Term Liability Policy No.',
        //         ],
        //         [
        //             'typ:value' => ($premium_type == 'own_damage') ? strtr($proposal->tp_start_date, '-', '/') : '',
        //             'typ:name' => 'Bundled/Long Term Policy Start Date',
        //         ],
        //         [
        //             'typ:value' => ($premium_type == 'own_damage') ? strtr($proposal->tp_end_date, '-', '/') : '',
        //             'typ:name' => 'Bundled/Long Term Policy Expiry Date',
        //         ],
        //     ];
        //     $properties = array_merge($properties, $tp_details);
        // }

        $properties = trim_array($properties);

        $proposal_array = [
            'typ:partyCode'       => $partyCode,
            'typ:partyStakeCode'  => $partyStakeCode,
            'typ:userCode' => 'USRPB',
            'typ:rolecode' => 'SUPERUSER',
            'typ:PRetCode' => '1',
            'typ:userId' => '',
            'typ:roleId' => '',
            'typ:userroleId' => '',
            'typ:branchcode' => '',
            'typ:PRetErr' => '',
            'typ:polBranchCode' => '',
            'typ:productName' => $product_name,
            'typ:policyHoldercode' => $proposal->state_id,
            'typ:eventDate' => $policy_start_date,
            'typ:netPremium' => '',
            'typ:termUnit' => 'G',
            'typ:grossPremium' => '',
            'typ:policyType' => '',
            'typ:branchCode' => '',
            'typ:polInceptiondate' => $policy_start_date,
            'typ:term' => '1',
            'typ:polEventEffectiveEndDate' => '',
            'typ:polStartdate' => $policy_start_date,
            'typ:productCode' => $product_code,
            'typ:policyHolderName' => 'SANJAY RANA',
            'typ:productId' => $product_id,
            'typ:serviceTax' => '',
            'typ:status' => '01',
            'typ:sumInsured' => '0',
            'typ:updateDate' => '',
            'typ:polExpirydate' => $policy_end_date,
            'typ:policyId' => '',
            'typ:polDetailLastUpdateDate' => '',
            'typ:quoteNo' => '',
            'typ:policyNo' => '',
            'typ:policyDetailid' => '',
            'typ:stakeCode' => 'POLICY-HOL',
            'typ:documentLink' => '',
            'typ:polLastUpdateDate' => '',
            'typ:properties' => $properties,
            'typ:risks' => [
                'typ:riskCode' => 'VEHICLE',
                'typ:riskSuminsured' => '568186',
                'typ:covers' => $covers,
                'typ:properties' => $risk_properties,
            ],
        ];
        // if($requestData->business_type == 'newbusiness'){
        //     $proposal_array['typ:term'] = '3';
        //     $proposal_array['typ:polExpirydate'] = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-'])))))); 
        // }
        $proposal_array = trim_array($proposal_array);
        $get_response = getWsData(
            config('constants.IcConstants.new_india.END_POINT_URL_NEW_INDIA'),
            $proposal_array,
            'new_india',
            [
                'root_tag' => 'typ:SaveQuote_ApproveProposalElement',
                'container' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:typ="http://iims.services/types/"><soapenv:Header /><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
                'authorization' => [config('constants.IcConstants.new_india.AUTH_NAME_NEW_INDIA'), config('constants.IcConstants.new_india.AUTH_PASS_NEW_INDIA')],
                'enquiryId' => $enquiryId,
                'requestMethod' => 'post',
                'productName' => $productData->product_name,
                'company' => 'new_india',
                'section' => $productData->product_sub_type_code,
                'method' => 'Save Quote And Approve Proposal',
                'transaction_type' => 'proposal',
            ]
        );
        $data = $get_response['response'];

        // dd($data);

        if ($data) {
            $proposal_resp = XmlToArray::convert((string) remove_xml_namespace($data));

            $SaveQuote_ApproveProposalResponseElement = array_search_key('SaveQuote_ApproveProposalResponseElement', $proposal_resp);
            if ($SaveQuote_ApproveProposalResponseElement) {
                if ($SaveQuote_ApproveProposalResponseElement['PRetCode'] == '0' || $SaveQuote_ApproveProposalResponseElement['netPremium'] == null || $SaveQuote_ApproveProposalResponseElement['netPremium'] == '') {
                    return [
                        'status' => 'false',
                        'message' => isset($SaveQuote_ApproveProposalResponseElement['PRetErr']) ? $SaveQuote_ApproveProposalResponseElement['PRetErr'] : 'Something went wrong. Please re-verify details which you have provided.',
                    ];
                } else {
                    $quoteNo = $SaveQuote_ApproveProposalResponseElement['quoteNo'];
                    $netPremium = round($SaveQuote_ApproveProposalResponseElement['netPremium']);

                    $pg_transaction_no = generate_random_number(floor((rand(0, 99999)) + 1), config('constants.IcConstants.NEW_INDIA.BROKER_NAME'), 5);#MIBL
                    UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'unique_proposal_id' => $quoteNo,
                            'pol_sys_id' => $SaveQuote_ApproveProposalResponseElement['policyId'],

                        ]);
                    if ($netPremium != 0) {

                        $str_arr = [
                            config('constants.IcConstants.new_india.MERCHANT_ID_NEW_INDIA_MOTOR'),
                            $pg_transaction_no,
                            'NA',
                            $netPremium,
                            'NA',
                            'NA',
                            'NA',
                            'INR',
                            'NA',
                            'R',
                            config('constants.IcConstants.new_india.SECURITY_ID_NEW_INDIA_MOTOR'),
                            'NA',
                            'NA',
                            'F',
                            config('constants.IcConstants.new_india.AGGREGATOR_ID_NEW_INDIA_MOTOR'),
                            $quoteNo,
                            'NA',
                            'NA',
                            'NA',
                            'NA',
                            'NA',
                            route('car.payment-confirm', ['new_india']),
                        ];

                        $str = implode('|', $str_arr);
                        return [
                            'status' => 'true',
                            'message' => $quoteNo,
                            'str' => $str,
                        ];
                    } else {
                        return [
                            'status' => 'false',
                            'message' => $SaveQuote_ApproveProposalResponseElement['PRetErr'],
                        ];
                    }
                }
            }
        } else {
            return [
                'status' => 'false',
                'message' => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.',
            ];
        }
    }
    public static function generate_checksum($req)
    {
        $checksum = hash('sha256', (($req) . '|' . config('constants.IcConstants.new_india.CHECKSUM_KEY_NEW_INDIA_MOTOR')));
        return strtoupper($checksum);
    }
    public static function confirm($request)
    {
        $response_data = $request->all();
        if (!isset($response_data['msg'])) {
            return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        $response_array = explode('|', ($response_data['msg']));
        $new_checksum_string = implode('|', array_slice($response_array, 0, -1));
        $final_checksum = strtoupper(hash('sha256', ($new_checksum_string . '|' . config('constants.IcConstants.new_india.CHECKSUM_KEY_NEW_INDIA_MOTOR'))));
        unset($new_checksum_string);
        if (empty($response_array[17])) {
            return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        $proposal = UserProposal::where('unique_proposal_id', $response_array[17])->first();
        $enquiryId = $proposal->user_product_journey_id;
        $policyid = QuoteLog::where('user_product_journey_id', $enquiryId)->pluck('master_policy_id')->first();
        $productData = getProductDataByIc($policyid);
        if (((end($response_array)) == $final_checksum) && ($response_array[14] == '0300')) {
            DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->where('active', 1)
                ->update(
                    [
                        'response' => $response_data['msg'],
                        'updated_at' => date('Y-m-d H:i:s'),
                        'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                    ]
                );
            PolicyDetails::Create(
                ['proposal_id' => $proposal->user_proposal_id],
                [
                    'policy_start_date' => $proposal->policy_start_date,
                    'idv' => $proposal->idv,
                    'ncb' => $proposal->ncb_discount,
                    'premium' => $proposal->final_payable_amount
                ]
            );
            $request = [
                'ns1:userCode' => config('constants.IcConstants.new_india.USERCODE_NEW_INDIA'),
                'ns1:rolecode' => config('constants.IcConstants.new_india.ROLE_CODE_NEW_INDIA'),#'SUPERUSER',
                'ns1:PRetCode' => '1',
                'ns1:userId' => config('constants.IcConstants.new_india.USERCODE_NEW_INDIA'),
                'ns1:stakeCode' => 'BROKER',
                'ns1:roleId' => '',
                'ns1:userroleId' => '',
                'ns1:branchcode' => '',
                'ns1:PRetErr' => '',
                'ns1:sourceOfCollection' => 'A',
                'ns1:collectionNo' => '',
                'ns1:receivedFrom' => '',
                'ns1:instrumentAmt' => $proposal->final_payable_amount,
                'ns1:collections' => [
                    'ns1:accountCode' => config('constants.IcConstants.new_india.ACCOUNTCODE_NEW_INDIA'),
                    'ns1:draweeBankName' => '',
                    'ns1:subCode' => '',
                    'ns1:draweeBankCode' => '',
                    'ns1:collectionMode' => 'ECS',
                    'ns1:debitCreditInd' => 'D',
                    'ns1:scrollNo' => '',
                    'ns1:chequeType' => '',
                    'ns1:quoteNo' => $proposal->unique_proposal_id,
                    'ns1:collectionAmount' => $proposal->final_payable_amount,
                    'ns1:chequeDate' => '',
                    'ns1:chequeNo' => $response_array[2],
                    'ns1:draweeBankBranch' => '',
                ],
                'ns1:quoteNo' => $proposal->unique_proposal_id,
                'ns1:collectionType' => 'A',
                'ns1:policyNo' => '',
                'ns1:documentLink' => '',
            ];
            $get_response = getWsData(
                config('constants.IcConstants.new_india.END_POINT_URL_NEW_INDIA'),
                $request,
                'new_india',
                [
                    'root_tag' => 'ns1:collectpremium_IssuepolElement',
                    'container' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body xmlns:ns1="http://iims.services/types/">#replace</soapenv:Body></soapenv:Envelope>',
                    'authorization' => [config('constants.IcConstants.new_india.AUTH_NAME_NEW_INDIA'), config('constants.IcConstants.new_india.AUTH_PASS_NEW_INDIA')],
                    'enquiryId' => $enquiryId,
                    'requestMethod' => 'post',
                    'productName' => $productData->product_name,
                    'company' => 'new_india',
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Policy Number Generation',
                    'transaction_type' => 'proposal',
                ]
            );
            $data = $get_response['response'];
            $collect_premium_array_response = XmlToArray::convert(remove_xml_namespace($data));
            $policy_no = array_search_key('policyNo', $collect_premium_array_response);
            if ($policy_no != '' || $policy_no != NULL && !empty($policy_no)) {
                $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'policy_no' => $policy_no,
                    ]);
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);
                PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([
                    'policy_number' => $policy_no,

                ]);
                $document_array =
                    [
                        'ns1:userCode' => config('constants.IcConstants.new_india.USERCODE_NEW_INDIA'),
                        'ns1:docs' =>
                            [
                                'ns1:value' => '',
                                'ns1:name' => ''
                            ],
                        'ns1:indexType' =>
                            [
                                'ns1:index' => '',
                                'ns1:type' => ''

                            ],
                        'ns1:policyId' => $proposal->pol_sys_id
                    ];
                $get_response = getWsData(
                    config('constants.IcConstants.new_india.END_POINT_URL_NEW_INDIA'),
                    $document_array,
                    'new_india',
                    [
                        'root_tag' => 'ns1:fetchDocumentNameElement',
                        'container' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body xmlns:ns1="http://iims.services/types/">#replace </soapenv:Body></soapenv:Envelope>',
                        'authorization' => [config('constants.IcConstants.new_india.AUTH_NAME_NEW_INDIA'), config('constants.IcConstants.new_india.AUTH_PASS_NEW_INDIA')],
                        'enquiryId' => $enquiryId,
                        'requestMethod' => 'post',
                        'productName' => $productData->product_name,
                        'company' => 'new_india',
                        'section' => $productData->product_sub_type_code,
                        'method' => 'Fetch policy Document',
                        'transaction_type' => 'proposal',
                    ]
                );
                $fetch_data = $get_response['response'];
                $fetch_array_response = XmlToArray::convert(remove_xml_namespace($fetch_data));
                if (isset($fetch_array_response['Body']['fetchDocumentNameResponseElement']['docs'][0]) && !empty($proposal->policy_no)) {
                    try {
                        $document_array = $fetch_array_response['Body']['fetchDocumentNameResponseElement']['docs'];
                        $policy_pdf_search_name = 'POLICYSCHEDULECIRTIFICATE';
                        $doc_id = 0;
                        foreach ($document_array as $key => $value) {
                            $doc_found = strpos($value['name'], $policy_pdf_search_name);
                            if ($doc_found != false) {
                                $doc_id = $key;
                            }
                        }
                        $document_id = $fetch_array_response['Body']['fetchDocumentNameResponseElement']['indexType'][$doc_id]['index'];
                        $doc = config('constants.IcConstants.new_india.POLICY_DWLD_LINK_NEW_INDIA') . $document_id;
                        #$data = file_get_contents($doc);
                        $pdf_name = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'new_india/' . md5($proposal->user_proposal_id) . '.pdf';
                        try {
                            $policy_pdf = Storage::put($pdf_name, httpRequestNormal($doc, 'GET', [], [], [], [], false)['response']);
                        } catch (\Throwable $th) {
                            PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                                ->update([
                                    'ic_pdf_url' => $doc
                                ]);
                            $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                            $data['ic_id'] = $proposal->ic_id;
                            $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                            updateJourneyStage($data);
                            #return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'CAR','SUCCESS'));
                            return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CAR', 'SUCCESS'));
                        }
                        unset($doc_id, $doc_found);
                        $doc_link = '';
                        if ($data != false && $proposal->policy_no != '') {
                            PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                                ->update([
                                    'pdf_url' => $pdf_name,
                                    'ic_pdf_url' => $doc,
                                    'status' => 'SUCCESS'
                                ]);

                            updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED']
                            ]);
                            return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CAR', 'SUCCESS'));
                        } else {
                            PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update(
                                [
                                    'policy_number' => $proposal->policy_no,
                                    'ic_pdf_url' => $data,
                                    #'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'new_india/'. md5($proposal->user_proposal_id). '.pdf',
                                    'status' => 'SUCCESS'
                                ]
                            );
                            updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                            ]);
                            return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CAR', 'SUCCESS'));
                        }
                    } catch (\Exception $e) {
                        updateJourneyStage([
                            'user_product_journey_id' => $proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);
                        return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CAR', 'SUCCESS'));

                    }
                }

                return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CAR', 'SUCCESS'));
            } else {
                return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CAR', 'SUCCESS'));
            }
        } else {
            DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('active', 1)
                ->update([
                    'response' => $request->All(),
                    'status' => STAGE_NAMES['PAYMENT_FAILED']
                ]);
            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['PAYMENT_FAILED']
            ]);

            return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id, 'CAR', 'FAILURE'));
        }
    }
    public static function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);

        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin('policy_details as pd', 'pd.proposal_id', '=', 'prr.user_proposal_id')
            ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'prr.user_product_journey_id')
            ->where('prr.user_product_journey_id', $user_product_journey_id)
            ->where(array('prr.active' => 1, 'prr.status' => STAGE_NAMES['PAYMENT_SUCCESS']))
            ->select(
                'up.user_proposal_id',
                'up.user_proposal_id',
                'up.proposal_no',
                'up.unique_proposal_id',
                'pd.policy_number',
                'pd.pdf_url',
                'pd.ic_pdf_url',
                'prr.order_id',
                'prr.response'
            )
            ->first();
        if ($policy_details == null) {
            $pdf_response_data = [
                'status' => false,
                'msg' => 'Data Not Found',
                'data' => []
            ];
            return response()->json($pdf_response_data);
        }
        if ($policy_details->ic_pdf_url == '') {
            $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
            $enquiryId = $proposal->user_product_journey_id;
            $policyid = QuoteLog::where('user_product_journey_id', $enquiryId)->pluck('master_policy_id')->first();
            $productData = getProductDataByIc($policyid);
            $document_array =
                [
                    'ns1:userCode' => config('constants.IcConstants.new_india.USERCODE_NEW_INDIA'),
                    'ns1:docs' =>
                        [
                            'ns1:value' => '',
                            'ns1:name' => ''
                        ],
                    'ns1:indexType' =>
                        [
                            'ns1:index' => '',
                            'ns1:type' => ''

                        ],
                    'ns1:policyId' => $proposal->pol_sys_id
                ];

            $get_response = getWsData(
                config('constants.IcConstants.new_india.END_POINT_URL_NEW_INDIA'),
                $document_array,
                'new_india',
                [
                    'root_tag' => 'ns1:fetchDocumentNameElement',
                    'container' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body xmlns:ns1="http://iims.services/types/">#replace </soapenv:Body></soapenv:Envelope>',
                    'authorization' => [config('constants.IcConstants.new_india.AUTH_NAME_NEW_INDIA'), config('constants.IcConstants.new_india.AUTH_PASS_NEW_INDIA')],
                    'enquiryId' => $enquiryId,
                    'requestMethod' => 'post',
                    'productName' => $productData->product_name,
                    'company' => 'new_india',
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Fetch policy Document',
                    'transaction_type' => 'proposal',
                ]
            );
            $fetch_data = $get_response['response'];
            $fetch_array_response = XmlToArray::convert((string) remove_xml_namespace($fetch_data));
            if (isset($fetch_array_response['Body']['fetchDocumentNameResponseElement']['docs'][0])) {
                try {
                    $document_array = $fetch_array_response['Body']['fetchDocumentNameResponseElement']['docs'];
                    $policy_pdf_search_name = 'POLICYSCHEDULECIRTIFICATE';
                    $doc_id = 0;
                    foreach ($document_array as $key => $value) {
                        $doc_found = strpos($value['name'], $policy_pdf_search_name);
                        if ($doc_found != false) {
                            $doc_id = $key;
                        }
                    }

                    $document_id = $fetch_array_response['Body']['fetchDocumentNameResponseElement']['indexType'][$doc_id]['index'];

                    if (!empty($document_id)) {
                        $doc = config('constants.IcConstants.new_india.POLICY_DWLD_LINK_NEW_INDIA') . '?policyNumber=' . $proposal->pol_sys_id . '&DocumentIdToView=' . $document_id;
                        $data = file_get_contents($doc);
                        unset($doc_id, $doc_found);
                        $doc_link = '';
                    } else {
                        $data = false;
                    }
                    if ($data != false) {
                        Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'new_india/' . md5($proposal->user_proposal_id) . '.pdf', $data);
                        PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'new_india/' . md5($proposal->user_proposal_id) . '.pdf',
                                'ic_pdf_url' => $doc
                            ]);

                        updateJourneyStage([
                            'user_product_journey_id' => $proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED']
                        ]);

                        $pdf_response_data = [
                            'status' => true,
                            'msg' => 'sucess',
                            'data' => [
                                'policy_number' => $policy_details->policy_number,
                                'pdf_link' => file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'new_india/' . md5($proposal->user_proposal_id) . '.pdf')
                            ]
                        ];
                    } else {
                        updateJourneyStage([
                            'user_product_journey_id' => $proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);
                        $pdf_response_data = [
                            'status' => false,
                            'msg' => 'Issue in pdf service',
                        ];
                    }
                } catch (\Exception $e) {
                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                    ]);
                    $pdf_response_data = [
                        'status' => false,
                        'msg' => 'Issue in pdf service',
                    ];
                }
            } else {
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);
                $pdf_response_data = [
                    'status' => false,
                    'msg' => 'Issue in pdf service',
                ];
            }

        } else {
            $pdf_response_data = [
                'status' => false,
                'msg' => STAGE_NAMES['POLICY_PDF_GENERATED'],
                'data' => []
            ];
        }
        return response()->json($pdf_response_data);
    }
}