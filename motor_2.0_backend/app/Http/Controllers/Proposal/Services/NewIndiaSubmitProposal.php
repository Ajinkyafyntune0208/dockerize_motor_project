<?php
namespace App\Http\Controllers\Proposal\Services;

use DateTime;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Car\NewIndiaPremiumDetailController;
use App\Models\AgentIcRelationship;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

class NewIndiaSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function submit($proposal, $request)
    {
        $enquiryId   = customDecrypt($request['userProductJourneyId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        $cvCategory = '';

        if ($productData->parent_id == 4 || $productData->parent_id == 17) {
            $cvCategory = 'GCV';
        } else if ($productData->parent_id == 8) {
            $cvCategory = 'PCV';
        } else {
            $cvCategory = 'MISC';
        }

        // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $quote_data = getQuotation(customDecrypt($request['userProductJourneyId']));

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
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }
        $veh_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        $city_name =  DB::table('master_rto')
            ->select('rto_name as city_name')
            ->where('rto_code', $requestData->rto_code)
            ->where('status', 'Active')
            ->first();

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $is_package         = (($premium_type == 'comprehensive') ? true : false);
        $is_liability       = (($premium_type == 'third_party') ? true : false);
        $is_od              = (($premium_type == 'own_damage') ? true : false);
        $reg_no = explode('-', strtoupper($requestData->vehicle_registration_no));
        $Color_as_per_RC_Book = 'As per RC book';
        $age = get_date_diff('month', $requestData->vehicle_register_date);
        $insurer = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();
        $insurer = keysToLower($insurer);

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts','compulsory_personal_accident', 'discounts')
            ->first();

        $anti_theft = 'N';
        $voluntary = 0;
        $legal_liability_to_paid_driver_cover_flag = 'N';
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
        $reg = explode("-", $proposal->vehicale_registration_number);
        $Registration_No_4 = $reg[3] ?? '';
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
        if ($requestData->business_type == 'newbusiness') {
            $policy_start_date = Date('d/m/Y');
            $policy_type = "New";
            $reg[0] = 'NEW';
            $reg[1] = '0';
            $reg[2] = 'none';
            $reg[3] = '0001';
            $new_vehicle = 'Y';
            $PreInsurerAdd = '';
        } else {
            $new_vehicle = 'N';
            $today_date = date('Y-m-d');
            $insured_name = '';
            if (new DateTime($requestData->previous_policy_expiry_date) > new DateTime($today_date)) {
                $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
            } else if (new DateTime($requestData->previous_policy_expiry_date) < new DateTime($today_date)) {
                $policy_start_date = date('d/m/Y', strtotime("+3 day"));
            } else {
                $policy_start_date = date('d/m/Y', strtotime("+1 day"));
            }
            $PreInsurerAdd =  $insurer->address_line_1;
            $PreInsurerAdd2 =  $insurer->address_line_2;
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
            if (!empty($additional['compulsory_personal_accident'])) { //cpa
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

        $address_data = [
            'address' => $proposal->address_line1,
            'address_1_limit'   => 69,
            'address_2_limit'   => 69            
        ];

        $getAddress = getAddress($address_data);

        $partyType = ($quote_data->vehicle_owner_type == 'I') ? 'I' : 'O';

        if ($partyType == 'I') {
            $policy_holder_array = [
                'ns1:userCode'        => 'USRPB',
                'ns1:rolecode'        => 'SUPERUSER', #'SUPERUSER',
                'ns1:PRetCode'        => '1',
                'ns1:userId'          => '',
                'ns1:stakeCode'       => 'POLICY-HOL',
                'ns1:roleId'          => '',
                'ns1:userroleId'      => '',
                'ns1:branchcode'      => '',
                'ns1:PRetErr'         => '',
                'ns1:startDate'       => $policy_start_date,
                'ns1:stakeName'       => '',
                'ns1:title'           => '',
                'ns1:typeOfOrg'       => '',
                'ns1:address'         => $getAddress['address_1'],
                'ns1:firstName'       => $proposal->first_name,
                'ns1:partyCode'       => '',
                'ns1:company'         => '',
                'ns1:sex'             => $sex,
                'ns1:address2'        => $getAddress['address_2'],
                'ns1:EMailid2'        => '',
                'ns1:partyStakeCode'  => 'POLICY-HOL',
                'ns1:partyType'       => $partyType,
                'ns1:regNo'           => '',
                'ns1:midName'         => '',
                'ns1:city'            => $proposal->city,
                'ns1:phNo3'           => $proposal->mobile_number,
                'ns1:regDate'         => '',
                'ns1:contactType'     => 'Permanent',
                'ns1:businessName'    => '',
                'ns1:status'          => '',
                'ns1:EMailid1'        => $proposal->email,
                'ns1:clientType'      => '',
                'ns1:birthDate'       => $birthDate,
                'ns1:lastName'        => $proposal->last_name,
                'ns1:sector'          => 'NP',
                'ns1:country'         => '',
                'ns1:pinCode'         => $proposal->pincode,
                'ns1:prospectId'      => '',
                'ns1:state'           => $proposal->state_id,
                'ns1:address3'        => $proposal->address_line3 . ' ' . $proposal->city,
                'ns1:phNo1'           => '',
                'ns1:businessAddress' => '',
                'ns1:phNo2'           => '',
                'ns1:partyName'       => '',
                'ns1:panNo'           => !is_null($proposal->pan_number) ? $proposal->pan_number : '',
                'ns1:gstRegIdType'    => '',
                'ns1:gstin'           => !is_null($proposal->gst_number) ? $proposal->gst_number : '',
            ];
        } else {
            $policy_holder_array = [
                'ns1:userCode'        => 'USRPB',
                'ns1:rolecode'        => 'SUPERUSER', #'SUPERUSER',
                'ns1:PRetCode'        => '0',
                'ns1:userId'          => '',
                'ns1:stakeCode'       => 'POLICY-HOL',
                'ns1:roleId'          => '',
                'ns1:userroleId'      => '',
                'ns1:branchcode'      => '',
                'ns1:PRetErr'         => '',
                'ns1:startDate'       => $policy_start_date,
                'ns1:stakeName'       => '',
                'ns1:title'           => '',
                'ns1:typeOfOrg'       => '',
                'ns1:address'         => $getAddress['address_1'],
                'ns1:firstName'       => '',
                'ns1:partyCode'       => '',
                'ns1:company'         => '',
                'ns1:sex'             => $sex,
                'ns1:address2'        => $getAddress['address_2'],
                'ns1:EMailid2'        => '',
                'ns1:partyStakeCode'  => 'POLICY-HOL',
                'ns1:partyType'       => $partyType,
                'ns1:regNo'           => '',
                'ns1:midName'         => '',
                'ns1:city'            => $proposal->city,
                'ns1:phNo3'           => $proposal->mobile_number,
                'ns1:regDate'         => '',
                'ns1:contactType'     => 'Permanent',
                'ns1:businessName'    => $requestData->business_type,
                'ns1:status'          => '',
                'ns1:EMailid1'        => $proposal->email,
                'ns1:clientType'      => '',
                'ns1:birthDate'       => '01/01/2000', //$birthDate,
                'ns1:lastName'        => '',
                'ns1:sector'          => 'NP',
                'ns1:country'         => '',
                'ns1:pinCode'         => $proposal->pincode,
                'ns1:prospectId'      => '',
                'ns1:state'           => $proposal->state_id,
                'ns1:address3'        => $proposal->address_line3 . ' ' . $proposal->city,
                'ns1:phNo1'           => '',
                'ns1:businessAddress' => '',
                'ns1:phNo2'           => '',
                'ns1:partyName'       => '',
                'ns1:panNo'           => !is_null($proposal->pan_number) ? $proposal->pan_number : '',
                'ns1:gstRegIdType'    => 'NCC',
                'ns1:gstin'           => !is_null($proposal->gst_number) ? $proposal->gst_number : '',
            ];
        }

        $policy_holder_array = trim_array($policy_holder_array);
        $get_response = getWsData(
            config('constants.IcConstants.new_india.END_POINT_URL_NEW_INDIA'),
            $policy_holder_array,
            'new_india',
            [
                'root_tag'      => 'ns1:createPolicyHol_GenElement',
                'container'     => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body xmlns:ns1="http://iims.services/types/">#replace</soapenv:Body></soapenv:Envelope>',
                'authorization' => [config('constants.IcConstants.new_india.AUTH_NAME_NEW_INDIA'), config('constants.IcConstants.new_india.AUTH_PASS_NEW_INDIA')],
                'enquiryId' => $enquiryId,
                'requestMethod' => 'post',
                'productName'  => $productData->product_name,
                'company'  => 'new_india',
                'section' => $productData->product_sub_type_code,
                'method' => 'Create Policy Holder',
                'transaction_type' => 'proposal',
            ]
        );
        $data = $get_response['response'];

        unset($policy_holder_array);
        if ($data) {
            $policy_holder_resp = XmlToArray::convert((string) remove_xml_namespace($data));
            $createPolicyHol_GenResponseElement = array_search_key('createPolicyHol_GenResponseElement', $policy_holder_resp);

            unset($policy_holder_resp);
            if ($createPolicyHol_GenResponseElement && ($createPolicyHol_GenResponseElement['PRetCode'] == '0') && (isset($createPolicyHol_GenResponseElement['partyCode']))) {
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
                $cover_pa_paid_driver = '0';
                $include_pa_cover_for_paid_driver = 'N';
                $cover_pa_unnamed_passenger = '0';
                $capital_si_for_unnamed_persons = '0';
                $individual_si_for_unnamed_person = '0';
                $include_pa_cover_for_unnamed_person = 'N';
                $lpg_cng_kit = '0.00';
                $bi_fuel_type = 'Bifuel';
                $extra_electrical_electronic_fittings = 'N';
                $extra_non_electrical_electronic_fittings = 'N';
                $total_value_of_electrical_electronic_fittings = '0';
                $total_value_of_non_electrical_electronic_fittings = '0';
                $type_of_fuel = $veh_data->motor_fule;
                $si_for_paid_drivers = '0';
                $llpd_flag = 'N';
                $ll_no_driver = '0';
                $ll_no_conductor = '0';
                $ll_no_cleaner = '0';
                $own_premises_limited = 'N';
                $tppd_flag = 'N';
                $tppd_amount = '0';
                $no_of_paid_conductors = '0';
                $no_of_paid_cleaners = '0';

                // if (!empty($additional['applicable_addons'])) {
                //     foreach ($additional['applicable_addons'] as $key => $data) {
                //         if ($data['name'] == 'Road Side Assistance' && $age <= 58) {
                //             $rsa_cover = 'Yes';
                //         }

                //         if ($data['name'] == 'Tyre Secure' && $age <= 34) {
                //             $tyre_secure_cover = 'Yes';
                //         }

                //         if ($data['name'] == 'Engine Protector' && $age <= 58) {
                //             $engine_protector_cover = 'Yes';
                //         }

                //         if ($data['name'] == 'NCB Protection' && $age <= 58 && $requestData->is_claim == 'N') {
                //             $ncb_protector_cover = 'Yes';
                //         }

                //         if ($data['name'] == 'Return To Invoice' && $age <= 34) {
                //             $return_to_invoice_cover = 'Yes';
                //         }

                //         if ($data['name'] == 'Consumable' && $age <= 58) {
                //             $cost_of_consumable = 'Yes';
                //         }
                //         if ($data['name'] == 'Loss of Personal Belongings' && $age <= 58) {
                //             $loss_of_personal_belongings_cover = 'Yes';
                //         }

                //         if ($data['name'] == 'Key Replacement' && $age <= 58) {
                //             $key_replacement_cover = 'Yes';
                //         }
                //     }
                // }

                if (!empty($additional['addons'])) {
                    foreach ($additional['addons'] as $key => $data) {
                        if ($data['name'] == 'Road Side Assistance' && $age <=58) {
                            $rsa_cover = 'Yes';
                        }

                        if ($data['name'] == 'Tyre Secure' && $age <=34) {
                            $tyre_secure_cover = 'Yes';
                        }

                        if ($data['name'] == 'Engine Protector' && $age <=58 ) {
                            $engine_protector_cover = 'Yes';
                        }

                        if ($data['name'] == 'NCB Protection' && $age <=58 && $requestData->is_claim == 'N') {
                            $ncb_protector_cover = 'Yes'; 
                        }

                        if ($data['name'] == 'Return To Invoice' && $age <=34) {
                            $return_to_invoice_cover = 'Yes';
                        }

                        if ($data['name'] == 'Consumable' && $age <=58) {
                            $cost_of_consumable = 'Yes';
                        }
                        if ($data['name'] == 'Loss of Personal Belongings' && $age <=58) {
                            $loss_of_personal_belongings_cover = 'Yes'; 
                        }

                        if ($data['name'] == 'Key Replacement' && $age <=58) {
                            $key_replacement_cover = 'Yes';
                        }
                    }
                }

                $is_geo_ext = false;
                $is_geo_code = 0;
                $srilanka = 0;
                $pak = 0;
                $bang = 0;
                $bhutan = 0;
                $nepal = 0;
                $maldive = 0;

                // POS
                $is_pos = 'No';
                $pos_name = null;
                $pos_name_uiic = null;
                $partyCode = null;
                $partyStakeCode = null;
                $pos_partyCode = null;
                $pos_partyStakeCode = null;
                $is_pos_testing_mode = config('IC.NEWINDIA.CV.TESTING_MODE') === 'Y';

                $pos_data = DB::table('cv_agent_mappings')
                     ->where('user_product_journey_id', $requestData->user_product_journey_id)
                     ->where('seller_type', 'P')
                     ->first();

                if ($pos_data && !$is_pos_testing_mode) {
                    //Properties
                    $is_pos = 'YES';
                    $pos_name = $pos_data->agent_name;
                    $pos_name_uiic = 'uiic';

                    //party
                    $partyCode = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
                    ->pluck('new_india_code')
                    ->first();
                    $pos_partyStakeCode = $pos_name;
                    if (empty($partyCode) || is_null($partyCode)) {
                         return [
                             'status' => false,
                             'premium_amount' => 0,
                             'message' => 'POS details Not Available'
                         ];
                    }
                    $partyStakeCode = 'POS';
                }
                if ($is_pos_testing_mode == 'Y') {
                     //properties
                    $is_pos = 'YES';
                    $pos_name = 'POS Applicable';
                    $pos_name_uiic = 'uiic';
                     //properties
                     //party
                    $partyCode = config('IC.NEWINDIA.CV.POS_PARTY_CODE'); //PP00000015
                    $partyStakeCode = 'POS';
                     //party
                }

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
                            $individual_si_for_unnamed_person = $data['sumInsured'];
                            $no_of_unnamed_persons = $veh_data->motor_carrying_capacity;
                            $include_pa_cover_for_unnamed_person = 'Y';
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

                if (!empty($additional['additional_covers'])) {
                    foreach ($additional['additional_covers'] as $data) {
                        if ($data['name'] == 'LL paid driver/conductor/cleaner') {
                            $legal_liability_to_paid_driver_cover_flag = 'Y';

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
                            $legal_liability_to_paid_driver_cover_flag = 'Y';
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
                            $type_of_fuel = 'CNG'; #$bi_fuel_type .' '.$veh_data->motor_fule;
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
                $idv = $quote->idv; //- $total_accessories_idv;
                $ex_showroom_price = $veh_data->motor_invoice;
                $insured_name = $proposal->previous_insurance_company;
                if (trim($insured_name) == '') {
                    $insured_name = 'Option24';
                }
                else if($requestData->previous_policy_type == 'Not sure'){
                    $insured_name = 'Option25';
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
                        $coverCode =  'LIABILITY';
                        $product_id = '194398713122007';
                        $product_code = 'CV';
                        $product_name = 'Commercial Vehicle';
                        break;
                    case 'own_damage':
                        $coverCode =  'ODWTOTADON'; //'ODWTHADDON';
                        $product_id = '120087123072019';
                        $product_code = 'SS';
                        $product_name = 'Standalone OD policy for PC';
                        break;
                }

                $covers = [
                    'typ:productCode'     => '',
                    'typ:policyDetailid'  => '',
                    'typ:coverCode'       => ($productData->zero_dep == 1) ? $coverCode : (($premium_type== 'own_damage') ? 'ODWTHADDON' : 'EHMNTCOVER'),
                    'typ:productId'       => '',
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
                            'typ:name' => 'Number of Additional  LL Employees',
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
                            'typ:value' => $no_of_paid_cleaners,
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
                            'typ:value' => $legal_liability_to_paid_driver_cover_flag,
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

                        // [
                        //     'typ:value' => 'Yes',
                        //     'typ:name' => 'Nil depreciation',
                        // ],

                        // addons 
                        [
                            'typ:value' => $engine_protector_cover,
                            'typ:name'  => 'Engine protect cover',
                        ],
                        [
                            'typ:value' => $cost_of_consumable,
                            'typ:name'  => 'Consumable Items Cover',
                        ],
                        [
                            'typ:value' => $tyre_secure_cover,
                            'typ:name'  => 'Tyre and Alloy Cover',
                        ],
                        [
                            'typ:value' => $key_replacement_cover,
                            'typ:name'  => 'Key Protect Cover',
                        ],
                        [
                            'typ:value' => $loss_of_personal_belongings_cover,
                            'typ:name'  => 'Personal Belongings Cover',
                        ],
                        [
                            'typ:value' => $rsa_cover,
                            'typ:name'  => 'Additional towing charges cover',
                        ],
                        [
                            'typ:value' => ($rsa_cover == 'Yes') ? '10000' : '0',
                            'typ:name'  => 'Additional towing charges Amount',
                        ],
                        [
                            'typ:value' => $ncb_protector_cover,
                            'typ:name'  => 'NCB Protection Cover',
                        ],
                        [
                            'typ:value' => $return_to_invoice_cover,
                            'typ:name'  => 'Return to invoice cover',
                        ],
                        [
                            'typ:value' => ($return_to_invoice_cover == 'Yes') ? $ex_showroom_price : '0',
                            'typ:name'  => 'Total Ex-Showroom Price',
                        ],
                        [
                            'typ:value' => ($return_to_invoice_cover == 'Yes') ? '1' : '0',
                            'typ:name'  => 'First Year Insurance Premium',
                        ],
                        [
                            'typ:value' => ($return_to_invoice_cover == 'Yes') ? '20' : '0',
                            'typ:name'  => 'Registration Charges',
                        ],
                    ],
                ];

                $covers = trim_array($covers);
                $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
                $vehicleDate = strtr($vehicleDate, ['-' => '/']);
                $first_reg_date = strtr($vehicleDate, ['-' => '/']);
                $existing_policy_expiry_date = strtr($requestData->previous_policy_expiry_date, ['-' => '/']);
                $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
                $manf_year = $motor_manf_year_arr[1];

                $risk_properties = [
                    [
                        'typ:value' => $is_pos,
                        'typ:name'  => $pos_name_uiic,
                    ],
                    [
                        'typ:value' => $first_reg_date, //$first_reg_date
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
                        'typ:value' => 'BLACK',
                        'typ:name' => 'Color of Vehicle',
                    ],
                    [
                        'typ:value' => 'Black',
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
                        'typ:value' => $vehicleDate,
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
                        'typ:value' => $Registration_No_4,
                        'typ:name' => "Registration No (4)"
                    ],
                    [
                        'typ:value' => $first_reg_date,
                        'typ:name' => "Registration Date"
                    ],
                    [
                        'typ:value' => "31/12/2030",
                        'typ:name' => "Registration Validity Date"
                    ],
                    [
                        'typ:value' => "NA",
                        'typ:name' => "Purpose of Using Passenger Vehicle(C1)"
                    ],
                    [
                        'typ:value' => $cvCategory == 'PCV' ? 'PUB' : 'NA',
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
                        'typ:value' => "GHF1A12268",
                        'typ:name' => "(*)Engine No"
                    ],
                    [
                        'typ:value' => "F1A17721",
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
                        'typ:value' => $own_premises_limited,
                        'typ:name' => "Vehicle use is limited to own premises"
                    ],
                    [
                        'typ:value' => ($premium_type == 'third_party') ? '0' : $no_claim_bonus,
                        'typ:name' => "NCB Applicable Percentage"
                    ],
                    [
                        'typ:value' => "NA",
                        'typ:name' => "Name of Previous Insurer"
                    ],
                    [
                        'typ:value' => "",
                        'typ:name' => "Previous Policy No"
                    ],
                    [
                        'typ:value' => "NA",
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
                        'typ:value' => "abcd",
                        'typ:name' => "License Issuing Authority for Owner Driver"
                    ],
                    [
                        'typ:value' => "G JENA",
                        'typ:name' => "Name of Nominee"
                    ],
                    [
                        'typ:value' => "55",
                        'typ:name' => "Age of Nominee"
                    ],
                    [
                        'typ:value' => "FATHER",
                        'typ:name' => "Relationship with the Insured"
                    ],
                    [
                        'typ:value' => "Male",
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
                        'typ:value' => ($premium_type == 'own_damage' || $requestData->vehicle_owner_type == "C") ? 'N' : 'Y',
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
                        'typ:value' => "M",
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
                        'typ:value' => "0",
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

                $policyHoldercode = $createPolicyHol_GenResponseElement['partyCode'];
                unset($reg[0], $reg[1], $reg[2], $reg[3], $createPolicyHol_GenResponseElement);
                $party = null;
                    if($pos_data){
                        $party=[
                            [
                                'typ:partyCode' =>  $partyCode,
                                'typ:partyStakeCode' =>  $partyStakeCode,
                            ],
                        ];
                    }
                    if($is_pos_testing_mode == 'Y'){
                        $party=[
                            [
                                'typ:partyCode' =>  $partyCode,
                                'typ:partyStakeCode'  =>  $partyStakeCode,
                            ],
                        ];
                    }

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

                $premium_array = [
                    'typ:userCode'                 => 'USRPB',
                    'typ:rolecode'                 => 'SUPERUSER',
                    'typ:PRetCode'                 => '1',
                    'typ:userId'                   => '',
                    'typ:roleId'                   => '',
                    'typ:userroleId'               => '',
                    'typ:branchcode'               => '',
                    'typ:PRetErr'                  => '',
                    'typ:polBranchCode'            => '',
                    'typ:productName'              => $product_name,
                    'typ:policyHoldercode'         => $proposal->state_id,
                    'typ:eventDate'                => $policy_start_date,
                    'typ:party'                    => $party,
                    'typ:netPremium'               => '',
                    'typ:termUnit'                 => 'G',
                    'typ:grossPremium'             => '',
                    'typ:policyType'               => '',

                    'typ:branchCode'               => '',
                    'typ:polInceptiondate'         => $policy_start_date,

                    // 'typ:polStartdate'             => $policy_start_date,
                    // 'typ:polExpirydate'            => $policy_end_date,

                    'typ:term'                     => '1',
                    'typ:polEventEffectiveEndDate' => '',
                    'typ:polStartdate'             => $policy_start_date,

                    'typ:productCode'              => $product_code,
                    'typ:policyHolderName'         => 'POLICY USER',
                    'typ:productId'                => $product_id,
                    'typ:serviceTax'               => '',
                    'typ:status'                   => '01',
                    'typ:sumInsured'               => '0',
                    'typ:updateDate'               => '',
                    'typ:polExpirydate'            => $policy_end_date,
                    'typ:policyId'                 => '',
                    'typ:polDetailLastUpdateDate'  => '',
                    'typ:quoteNo'                  => '',
                    'typ:policyNo'                 => '',
                    'typ:policyDetailid'           => '',
                    'typ:stakeCode'                => 'POLICY-HOL',
                    'typ:documentLink'             => '',
                    'typ:polLastUpdateDate'        => '',
                    'typ:properties'               => $properties,
                    // 'typ:party'=> $party,
                    'typ:risks'                    => [
                        'typ:riskCode'       => 'VEHICLE',
                        'typ:riskSuminsured' => '568186',
                        'typ:covers'         => $covers,
                        'typ:properties'     => $risk_properties,
                        // 'typ:party'          => $party,
                    ],
                ];
                // if($requestData->business_type == 'newbusiness'){
                //     $premium_array['typ:term'] = '3';
                //     $premium_array['typ:polExpirydate'] = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-'])))))); 
                // }

                $premium_array = trim_array($premium_array);

                $get_response = getWsData(
                    config('constants.IcConstants.new_india.END_POINT_URL_NEW_INDIA'),
                    $premium_array,
                    'new_india',
                    [
                        'root_tag'      => 'typ:calculatePremiumMasterElement',
                        'container'     => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:typ="http://iims.services/types/"><soapenv:Header /><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
                        'authorization' => [config('constants.IcConstants.new_india.AUTH_NAME_NEW_INDIA'), config('constants.IcConstants.new_india.AUTH_PASS_NEW_INDIA')],
                        'enquiryId' => $enquiryId,
                        'requestMethod' => 'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'new_india',
                        'section' => $productData->product_sub_type_code,
                        'method' => 'Premium Calculation',
                        'transaction_type' => 'proposal',
                    ]
                );
                $data = $get_response['response'];
                unset($covers, $properties, $premium_array);

                if ($data) {
                    $premium_resp = XmlToArray::convert((string) remove_xml_namespace($data));
                    $PRetCode = array_search_key('PRetCode', $premium_resp);
                    $basic_tp_key = 0;
                    $electrical_key = 0;
                    $nonelectrical_key = 0;
                    $basic_od_key = 0;
                    $calculated_ncb = 0;
                    $ll_paid_driver = 0;
                    $pa_paid_driver = 0;
                    $pa_unnamed_person = 0;
                    $additional_od_prem_cnglpg = 0;
                    $additional_tp_prem_cnglpg = 0;
                    $anti_theft_discount_key = 0;
                    $aai_discount_key = 0;
                    $total_od = 0;
                    $total_tp = 0;
                    $total_discount = 0;
                    $pa_owner = 0;
                    $zero_dep_amount = 0;
                    $eng_prot = 0;
                    $ncb_prot = 0;
                    $rsa = 0;
                    $tyre_secure_amount = 0;
                    $return_to_invoice_amount = 0;
                    $consumable_amount = 0;
                    $ncb_prot_amount = 0;
                    $belonging_amount = 0;
                    $total_tp_premium = 0;
                    $pa_owner_driver = 0;
                    $od_discount = 0;
                    $voluntary_Discount = 0;
                    $net_total_premium = 0;

                    if ($PRetCode == '0') {
                        $properties = array_search_key('properties', $premium_resp);

                        $premium_resp = array_change_key_case_recursive($properties);

                        unset($PRetCode, $properties);
                        foreach ($premium_resp as $key => $cover) {
                            if ($cover['name'] === 'Calculated Premium') {
                                $calculated_premium = $cover['value'];
                            }

                            if ($cover['name'] === 'Net Total Premium') {
                                $net_total_premium = $cover['value'];
                            }

                            if ($cover['name'] === 'Service Tax') {
                                $service_tax = $cover['value'];
                            }

                            if (in_array($cover['name'], ['Additional Premium for Electrical fitting', 'IMT Rate Additional Premium for Electrical fitting'])) {
                                $electrical_key = $cover['value'];
                            }
                            if (in_array($cover['name'], ['Additional Premium for Non-Electrical fitting', 'IMT Rate Additional Premium for Non-Electrical fitting'])) {
                                $nonelectrical_key = $cover['value'];
                            }
                            // elseif ($cover['name'] === 'Basic OD Premium')
                            // {
                            //     $basic_od_key = $cover['value'];
                            // }
                            //elseif ($cover['name'] === 'Basic OD Premium_IMT')
                            elseif ($cover['name'] === 'IMT Rate Basic OD Premium') {
                                $basic_od_key = $cover['value'];
                            } elseif ($cover['name'] === 'Basic TP Premium' && $requestData->business_type != 'newbusiness') {
                                $basic_tp_key = $cover['value'];
                            }
                            //new business tp premium
                            elseif (in_array($cover['name'], ['(#)Total TP Premium', '(#)Total TP Premium for 2nd Year', '(#)Total TP Premium for 3rd Year']) && $requestData->business_type == 'newbusiness') {
                                $basic_tp_key += $cover['value'];
                            } elseif ($cover['name'] === 'Calculated NCB Discount') {
                                $calculated_ncb = $cover['value'];
                            } elseif ($cover['name'] === 'OD Premium Discount Amount') {
                                $od_discount = round($cover['value']);
                            } elseif ($cover['name'] === 'Compulsory PA Premium for Owner Driver') {
                                $pa_owner_driver = $cover['value'];
                            } elseif ($cover['name'] === 'Additional premium for LL to paid driver') {
                                $ll_paid_driver = $cover['value'];
                            } elseif ($cover['name'] === 'PA premium for Paid Drivers And Others') {
                                $pa_paid_driver = $cover['value'];
                            } elseif ($cover['name'] === 'PA premium for UnNamed/Hirer/Pillion Persons') {
                                $pa_unnamed_person = $cover['value'];
                            } elseif ($cover['name'] == 'Additional OD Premium for CNG/LPG') {
                                $additional_od_prem_cnglpg = $cover['value'];
                            } elseif ($cover['name'] == 'Additional TP Premium for CNG/LPG') {
                                $additional_tp_prem_cnglpg = $cover['value'];
                            }
                            //addons
                            elseif (($productData->zero_dep == '0') && ($cover['name'] === 'Premium for nil depreciation cover')) {
                                $zero_dep_key = $cover['value'];
                            } elseif ($cover['name'] == 'Engine Protect Cover Premium') {
                                $engine_protector_key = $cover['value'];
                            } elseif ($cover['name'] == 'Consumable Items Cover Premium') {
                                $consumable_key = $cover['value'];
                            } elseif ($cover['name'] == 'NCB Protection Cover Premium') {
                                $ncb_protecter_key = $cover['value'];
                            } elseif ($cover['name'] == 'Tyre and Alloy Cover Premium') {
                                $tyre_secure_key = $cover['value'];
                            } elseif ($cover['name'] == 'Personal Belongings Cover Premium') {
                                $personal_belongings_key = $cover['value'];
                            } elseif ($cover['name'] == 'Additional Towing Charges Cover Premium') {
                                $rsa_key = $cover['value'];
                            } elseif ($cover['name'] == 'Key Protect Cover Premium') {
                                $key_protect_key = $cover['value'];
                            } elseif ($cover['name'] == 'Return to Invoice Cover Premium') {
                                $rti_cover_key = $cover['value'];
                            } elseif ($cover['name'] == 'Calculated Discount for Anti-Theft Devices') {
                                $anti_theft_discount_key = $cover['value'];
                            } elseif ($cover['name'] == 'Calculated Discount for Membership of recognized Automobile Association') {
                                $aai_discount_key = $cover['value'];
                            } elseif ($cover['name'] == 'Calculated Voluntary Deductible Discount') {
                                $voluntary_Discount = $cover['value'];
                            }
                        }

                        if ($premium_type != 'third_party') {
                            $total_od = $basic_od_key + $electrical_key +
                                $nonelectrical_key + $additional_od_prem_cnglpg;
                        } else {
                            $total_od = '0';
                        }


                        if ($premium_type != 'own_damage') {
                            $total_tp = $basic_tp_key + $pa_paid_driver + $pa_unnamed_person + $additional_tp_prem_cnglpg + $ll_paid_driver;
                        }
                        $total_discount = $anti_theft_discount_key + $aai_discount_key + $od_discount + $voluntary_Discount;
                        if ($premium_type != 'third_party') {
                            if ($requestData->is_claim == 'N') {
                                $total_discount = $total_discount + $calculated_ncb;
                            }
                        }
                        if ($productData->zero_dep == '0') {
                            $zero_dep_amount = $zero_dep_key;
                            $eng_prot = $engine_protector_key;
                            $rsa = $rsa_key;
                            $tyre_secure_amount = $tyre_secure_key;
                            $return_to_invoice_amount = $rti_cover_key;
                            $consumable_amount = $consumable_key;
                            $ncb_prot_amount = $ncb_protecter_key;
                            $belonging_amount = $personal_belongings_key;
                        }
                        $addon_premium = $zero_dep_amount + $eng_prot + $rsa + $tyre_secure_amount + $return_to_invoice_amount + $consumable_amount + $ncb_prot_amount + $belonging_amount;

                        $vehicleDetails = [
                            'manf_name' => $veh_data->motor_make,
                            'model_name' => $veh_data->motor_model,
                            'version_name' => $veh_data->motor_variant,
                            'seating_capacity' => $veh_data->motor_carrying_capacity,
                            'carrying_capacity' => $veh_data->motor_carrying_capacity,
                            'cubic_capacity' => $veh_data->motor_cc,
                            'fuel_type' =>  $veh_data->motor_fule,
                            'vehicle_type' => 'TAXI',
                        ];
                        $save = UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'od_premium' => $total_od,
                                'tp_premium' => $total_tp,
                                'ncb_discount' => $calculated_ncb,
                                'addon_premium' => $addon_premium,
                                'total_premium' => $calculated_premium,
                                'service_tax_amount' => $service_tax,
                                'final_payable_amount' => $net_total_premium,
                                'cpa_premium' => $pa_owner_driver,
                                //'policy_no' => $proposal_response->policyNumber,
                                'proposal_no' => '',
                                'unique_proposal_id' => '',
                                'is_policy_issued' => 'Accepted',
                                'policy_start_date' => str_replace('/', '-', $policy_start_date),
                                'policy_end_date' => str_replace('/', '-', $policy_end_date),
                                'ic_vehicle_details' => $vehicleDetails,
                                'additional_details_data' => $policyHoldercode,
                            ]);
                        updateJourneyStage([
                            'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                            'ic_id' => $productData->company_id,
                            'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                            'proposal_id' => $proposal->user_proposal_id,
                        ]);

                        NewIndiaPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                        $kyc_url = '';
                        $is_kyc_url_present = false;
                        $kyc_message = '';
                        $kyc_status = true;

                        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                            $kyc_verification = self::ckycVerifications($proposal, $request, $policyHoldercode, $enquiryId);
                            extract($kyc_verification);

                            $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
                        }

                        return response()->json([
                            'status' => true,//$kyc_status,
                            'msg' => $kyc_status ? "Proposal Submitted Successfully!" : $kyc_message,
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $proposal->user_product_journey_id,
                                'proposalNo' => '',
                                'finalPayableAmount' => $net_total_premium,
                                'is_breakin' => 'N',
                                'kyc_url' => $kyc_url,
                                'is_kyc_url_present' => $is_kyc_url_present,
                                'kyc_message' => $kyc_message,
                                'kyc_status' => $kyc_status,
                                'verification_status' => $kyc_status
                            ]
                        ]);
                    }
                    $PRetErr = array_search_key('PRetErr', $premium_resp);
                    return response()->json([
                        'status'  => false,
                        'msg' => $PRetErr
                    ]);
                } //proposal
                return response()->json([
                    'status'  => false,
                    'msg' => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.'
                ]);
            } else {
                return response()->json([
                    'status'  => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'msg' => !empty($createPolicyHol_GenResponseElement['PRetErr']) && !is_array($createPolicyHol_GenResponseElement['PRetErr']) ? $createPolicyHol_GenResponseElement['PRetErr'] : 'Error in Create Policy Holder service ' #$createPolicyHol_GenResponseElement['PRetErr']

                ]);
            }
        } else {
            return response()->json([
                'status'  => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.'
            ]);
        }
    }

    public static function ckycVerifications(UserProposal $proposal, $request, $policyHoldercode, $enquiryId)
    {
        $ckyc_controller = new CkycController;

        $ckyc_modes = [
            'ckyc_number' => 'ckyc_number',
            'pan_card' => 'pan_number_with_dob',
            'aadhar_card' => 'aadhar',
            'passport' => 'passport',
            'driving_license' => 'driving_licence',
            'voter_id' => 'voter_id'
        ];

        $ckyc_response = $ckyc_controller->ckycVerifications(new Request([
            'companyAlias' => 'new_india',
            'enquiryId' => $request['enquiryId'],
            'mode' => $ckyc_modes[$proposal->ckyc_type],
            'policyHolderCode' => $policyHoldercode
        ]))->getOriginalContent();

        $kyc_url = '';
        $is_kyc_url_present = false;
        $kyc_message = '';
        $kyc_status = false;

        if ($ckyc_response['status']) {
            $ckyc_response['data']['customer_details'] = [
                'name' => $ckyc_response['data']['customer_details']['fullName'] ?? null,
                'mobile' => $ckyc_response['data']['customer_details']['mobileNumber'] ?? null,
                'dob' => $ckyc_response['data']['customer_details']['dob'] ?? null,
                'address' => $ckyc_response['data']['customer_details']['address'] ?? null,
                'pincode' => $ckyc_response['data']['customer_details']['pincode'] ?? null,
                'email' => $ckyc_response['data']['customer_details']['email'] ?? null,
                'pan_no' => $ckyc_response['data']['customer_details']['panNumber'] ?? null,
                'ckyc' => $ckyc_response['data']['customer_details']['ckycNumber'] ?? null
            ];

            $ckyc_response_to_save['response'] = $ckyc_response;

            $updated_proposal = $ckyc_controller->saveCkycResponseInProposal(new Request([
                'company_alias' => 'kotak',
                'trace_id' => $request['enquiryId']
            ]), $ckyc_response_to_save, $proposal);

            foreach ($updated_proposal as $key => $u) {
                $proposal->$key = $u;
            }

            $proposal->save();

            $kyc_status = true;
        } else {
            $kyc_url = $ckyc_response['data']['redirection_url'] ?? null;
            $is_kyc_url_present = isset($ckyc_response['data']['redirection_url']);
            $kyc_message = 'CKYC Verification Failed';
        }

        return compact('kyc_status', 'is_kyc_url_present', 'kyc_message', 'kyc_url');
    }
}