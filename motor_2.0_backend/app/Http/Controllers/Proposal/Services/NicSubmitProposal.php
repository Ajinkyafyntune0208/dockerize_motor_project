<?php

namespace App\Http\Controllers\Proposal\Services;

use Config;
use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use Illuminate\Support\Str;
use App\Models\UserProposal;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\PreviousInsurerList;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;


include_once app_path() . '/Helpers/CvWebServiceHelper.php';

class NicSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {

        $enquiryId     = customDecrypt($request['userProductJourneyId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }
        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
        $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
        $is_od          = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);
        $is_individual  = (($requestData->vehicle_owner_type == 'I') ? true : false);
        $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

        $is_breakin     = (
            (
                (strpos($requestData->business_type, 'breakin') === false) || (!$is_liability && $requestData->previous_policy_type == 'Third-party')
            ) ? false
            : true);

        $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);

        $quote_log_data = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->first();

        $idv = $quote_log_data->idv;
        $parent_code = get_parent_code($productData->product_sub_type_id);
        if ($parent_code == 'GCV') {

            $newmmv = [
                'status' => true,
                'data' => [
                    'unique_id' => 'IBB101001002DL',
                    'ibb_code' => 'IBB101001002',
                    'make_name' => 'ASHOK LEYLAND',
                    'model_name' => 'STILE',
                    'model_period' => '(2013-2015)',
                    'variant_name' => 'LE 8 STR',
                    'state_name' => 'Delhi',
                    'state_code' => 'DL',
                    'iib_tac_code' => '22001',
                    'body_type' => 'MPV',
                    'fuel_type' => 'DIESEL',
                    'seating_capacity' => '2',
                    'cubic_capacity' => '1461',
                    'ex_showroom_price' => '752173',
                    'idv_6mon' => '0',
                    'idv_6monto1yr' => '0',
                    'idv_1_2_yr' => '0',
                    'idv_2_3_yr' => '0',
                    'idv_3_4_yr' => '0',
                    'idv_4_5_yr' => '0',
                    'idv_5_6_yr' => '338478',
                    'idv_6_7_yr' => '300869',
                    'idv_7_8_yr' => '274543',
                    'idv_8_9_yr' => '246713',
                    'idv_9_10_yr' => '0',
                    'idv_10_11_yr' => '',
                    'idv_11_12_yr' => '',
                    'idv_12_13_yr' => '',
                    'idv_13_14_yr' => '',
                    'idv_14_15_yr' => '',
                    'idv_15_16_yr' => '',
                    'idv_16_17_yr' => '',
                    'idv_17_18_yr' => '',
                    'idv_18_19_yr' => '',
                    'idv_19_20_yr' => '',
                    'nic_make_code' => '894023',
                    'nic_model_code' => '894023042',
                    'nic_model_name' => 'STILE (2013-2015)',
                    'nic_variant_code' => '13571',
                    'nic_variant_name' => 'STILE LE 8 STR (2013-2015)',
                    'nic_state_code' => '5',
                    'model_period_start' => '2019',
                    'model_period_end' => '2019',
                    'nic_body_type_code' => '13',
                    'nic_fuel_type_code' => '2',
                    'sheet_date' => '3-1-2021',
                    'sheet_version' => '30',
                    'status' => 'EXIST',
                    'insert_time' => '7-31-2018 10:52 PM',
                    'update_time' => '4-22-2021 10:55 PM',
                    'field01' => '',
                    'field02' => '',
                    'field03' => '',
                    "fyntune_version" =>  [
                        "version_id" => "GCV4W1511210775",
                        "version_name" => "1.3 LXI",
                        "cubic_capacity" => "1343",
                        "fuel_type" => "PETROL",
                        "seating_capacity" => "5",
                        "mmv_cholla_mandalam" => "1030",
                    ],
                    "weight" => '134',
                ]

            ];

            $mmv = $newmmv;
        } else{
        $mmv = get_mmv_details($productData, $requestData->version_id, 'nic');
        }
        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return    [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        if (empty($mmv->fyntune_version['version_id']) || $mmv->fyntune_version['version_id'] == '') {
            return camelCase([
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
            ]);
        } elseif ($mmv->fyntune_version['version_id'] == 'DNE') {
            return camelCase([
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]);
        }
        $mmv->idv       = (string)($idv);
        $idv2           = $mmv->idv_1_2_yr;
        $idv3           = $mmv->idv_2_3_yr;
        $fuel           = $mmv->nic_fuel_type_code;

        $mmv_data = [
            'manf_name'             => $mmv->make_name,
            'model_name'            => $mmv->model_name,
            'version_name'          => $mmv->variant_name,
            'seating_capacity'      => $mmv->seating_capacity,
            'carrying_capacity'     => $mmv->seating_capacity - 1,
            'cubic_capacity'        => $mmv->cubic_capacity,
            'fuel_type'             => $mmv->fuel_type,
            'gross_vehicle_weight'  => '',
            'vehicle_type'          => 'Cv',
            'version_id'            => $mmv->fyntune_version['version_id'],
            'idv'                   => $mmv->idv,
            'idv2'                  => $idv2,
            'idv3'                  => $idv3,
            'time_period'           => [
                'manufacture_year'      => $requestData->manufacture_year,
                'veh_start_period'      => $mmv->model_period_start,
                'veh_end_period'        => $mmv->model_period_end,
            ]
        ];

        $rto_code       = $requestData->rto_code;

        $rto_data = DB::table('nic_rto_master')->where('rto_number', strtr($requestData->rto_code, ['-' => '']))->first();

        $customer_type  = $requestData->vehicle_owner_type == "I" ? "Individual" : "organization";

        $btype_code     = $requestData->business_type == "rollover" ? "2" : "1";
        $btype_name     = $requestData->business_type == "rollover" ? "Roll Over" : "New Business";

        $date1          = new DateTime($requestData->vehicle_register_date);
        $date2          = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval       = $date1->diff($date2);
        $age            = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age    = floor($age / 12);

        $vehicle_in_90_days = 0;

        $motor_manf_date = '01-' . $requestData->manufacture_year;

        $current_date       = date('Y-m-d');

        if ($is_new) {
            $policy_start_date  = date('d-m-Y');
            $policy_end_date    = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 3 year'));
        } else {
            $policy_start_date  = date('Ymd', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));

            if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)) {
                $policy_start_date = date('Ymd');
            }

            $policy_end_date    = date('Ymd', strtotime($policy_start_date . ' - 1 days + 1 year'));
        }

        if ($proposal->vehicale_registration_number == 'NEW') {
            $vehicle_register_no = 'NEW';
        } else {
            $vehicle_register_no    = explode('-', $proposal->vehicale_registration_number);

            $vehicle_register_no = $vehicle_register_no[0]
                . '-' . $vehicle_register_no[1]
                . '-' . $vehicle_register_no[2]
                . '-' . $vehicle_register_no[3];
            $previousInsurerList    = PreviousInsurerList::where([
                'company_alias' => 'nic',
                'code' => $proposal->previous_insurance_company
            ])->first();
        }


        // addon
        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();

        $RepairOfGlasPlastcFibNRubrGlas = $DepreciationReimbursement = $NoOfClaimsDepreciation = $ConsumablesExpenses = $LossOfPersonalBelongings = $EngineSecure = $TyreSecure = $KeyReplacement = $RoadsideAssistance = $ReturnToInvoice = $NCBProtectionCover = $EmergTrnsprtAndHotelExpense = $ac_opted_in_pp = "N";


        $selected_addons        = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons                 = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories            = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $additional_covers      = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $discounts              = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

        $Electricalaccess = $externalCNGKIT = $PAforUnnamedPassenger = $PAforaddionaldPaidDriver = $PAforaddionaldPassenger = $NonElectricalaccess = $PAPaidDriverConductorCleaner = $llpaidDriver = "N";

        // additional covers
        $externalCNGKITSI = $ElectricalaccessSI = $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI = $NonElectricalaccessSI = $PAPaidDriverConductorCleanerSI = $llpaidDriverSI = 0;

        $is_anti_theft = $is_voluntary_access = $autoMobileAssociation = $Electricalaccess = $NonElectricalaccess = $externalCNGKIT = $PAPaidDriverConductorCleaner = $PAforaddionaldPaidDriver = $PAforUnnamedPassenger = $llpaidDriver = false;

        foreach ($accessories as $key => $value) {
            if (in_array('Electrical Accessories', $value)) {
                $Electricalaccess               = true;
                $ElectricalaccessSI             = $value['sumInsured'];
            }

            if (in_array('Non-Electrical Accessories', $value)) {
                $NonElectricalaccess            = true;
                $NonElectricalaccessSI          = $value['sumInsured'];
            }

            if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                $externalCNGKIT                 = true;
                $externalCNGKITSI               = $value['sumInsured'];
            }

            if (in_array('PA To PaidDriver Conductor Cleaner', $value)) {
                $PAPaidDriverConductorCleaner   = true;
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }
        }

        foreach ($additional_covers as $key => $value) {
            if (in_array('PA cover for additional paid driver', $value)) {
                $PAforaddionaldPaidDriver       = true;
                $PAforaddionaldPaidDriverSI     = $value['sumInsured'];
            }

            if (in_array('Unnamed Passenger PA Cover', $value)) {
                $PAforUnnamedPassenger          = true;
                $PAforUnnamedPassengerSI        = $value['sumInsured'];
            }

            if (in_array('LL paid driver', $value)) {
                $llpaidDriver                   = true;
                $llpaidDriverSI                 = $value['sumInsured'];
            }
        }

        foreach ($discounts as $key => $value) {
            if ($value['name'] == 'anti-theft device' && !$is_liability) {
                $is_anti_theft = true;
            }

            if ($value['name'] == 'voluntary_insurer_discounts' && isset($value['sumInsured'])) {
                $is_voluntary_access = true;
                $voluntary_excess_amt = $value['sumInsured'];
            }

            if ($value['name'] == 'TPPD Cover' && !$is_od) {
                $is_tppd = true;
                $tppd_amt = '9999';
            }
        }

        $consumable                         = 'N';
        $loss_of_belongings                 = 'N';
        $engine_secure                      = 'N';
        $tyre_secure                        = 'N';
        $key_replacement                    = 'N';
        $road_side_assistance               = 'N';
        $return_to_invoice                  = 'N';

        foreach ($addons as $key => $value) {
            if (in_array('Road Side Assistance', $value)) {
                $road_side_assistance = "Y";
            }
            if (in_array('Key Replacement', $value)) {
                $key_replacement = "Y";
            }
            if (in_array('Engine Protector', $value)) {
                $engine_secure = "Y";
            }
            if (in_array('Consumable', $value)) {
                $consumable = "Y";
            }
            if (in_array('Tyre Secure', $value)) {
                $tyre_secure = "Y";
            }
            if (in_array('Return To Invoice', $value)) {
                $return_to_invoice = "Y";
            }
            if (in_array('Loss of Personal Belongings', $value)) {
                $loss_of_belongings = "Y";
            }
        }

        $addon['zero_dep']          = ((!$is_liability && ($vehicle_age < 5) && $is_zero_dep) ? true : false);
        $addon['rsa']               = ((!$is_liability && ($road_side_assistance == 'Y')) ? true : false);
        $addon['engine_protect']    = false; //((!$is_liability && ($vehicle_age < 5) && ($engine_secure == 'Y')) ? true : false);
        $addon['rti']               = ((!$is_liability && ($vehicle_age < 3) && ($return_to_invoice == 'Y')) ? true : false);

        $is_cpa = false;

        $is_pa_unnamed      = (!$is_od && $PAforUnnamedPassenger) ? true : false;
        $is_pa_paid_driver  = (!$is_od && $PAforaddionaldPaidDriver) ? true : false;
        $pa_named           = false;

        $is_applicable['legal_liability']                   = false; //(!$is_od && $llpaidDriver) ? true : false;
        $is_applicable['motor_anti_theft']                  = ((!$is_liability && $is_anti_theft) ? true : false);

        $is_applicable['motor_electric_accessories']        = ((!$is_liability && $Electricalaccess) ? true : false);
        $is_applicable['motor_non_electric_accessories']    = ((!$is_liability && $NonElectricalaccess) ? true : false);
        $is_applicable['motor_lpg_cng_kit']                 = ((!$is_liability && $externalCNGKIT) ? true : false);
        $is_applicable['automobile_association']            = ((!$is_liability && $autoMobileAssociation) ? true : false);
        // end additional covers

        $proposal_addtional_details = json_decode($proposal->additional_details, true);

        $is_sapa = false;
        $is_valid_license       = 'Y';
        $sapa['applicable']     = 'N';
        $sapa['insured']        = '';
        $sapa['policy_no']      = '';
        $sapa['start_date']     = '';
        $sapa['end_date']       = '';

        if (isset($selected_addons->compulsory_personal_accident[0]['name'])) {
            $is_cpa = true;
            $driver_declaration    = "ODD01";
        } else {
            $cpa_cover = false;
            if ($customer_type == 'Individual') {
                $sapa['applicable'] = 'Y';
                if ($proposal_addtional_details['prepolicy']['reason'] == "I have another motor policy with PA owner driver cover in my name") {
                    $is_sapa                = true;
                    $sapa['insured']        = $proposal_addtional_details['prepolicy']['cPAInsComp'];
                    $sapa['policy_no']      = $proposal_addtional_details['prepolicy']['cPAPolicyNo'];
                    $sapa['start_date']     = $proposal_addtional_details['prepolicy']['cpaPolicyStartDate'];
                    $sapa['end_date']       = $proposal_addtional_details['prepolicy']['cpaPolicyEndDate'];
                } elseif ($proposal_addtional_details['prepolicy']['reason'] == "I have another PA policy with cover amount of INR 15 Lacs or more") {
                    $is_sapa                = true;
                    $sapa['insured']        = $proposal_addtional_details['prepolicy']['cPAInsComp'];
                    $sapa['policy_no']      = $proposal_addtional_details['prepolicy']['cPAPolicyNo'];
                    $sapa['start_date']     = $proposal_addtional_details['prepolicy']['cpaPolicyStartDate'];
                    $sapa['end_date']       = $proposal_addtional_details['prepolicy']['cpaPolicyEndDate'];
                } elseif ($proposal_addtional_details['prepolicy']['reason'] == "I do not have a valid driving license.") {
                    $driver_declaration    = "ODD02";
                    $is_valid_license      = 'N';
                } else {
                    $driver_declaration    = "ODD01";
                }
            }
        }
        if ($is_od) {
            $tp_insured         = $proposal_addtional_details['prepolicy']['tpInsuranceCompany'];
            $tp_insurer_name    = $proposal_addtional_details['prepolicy']['tpInsuranceCompanyName'];
            $tp_start_date      = $proposal_addtional_details['prepolicy']['tpStartDate'];
            $tp_end_date        = $proposal_addtional_details['prepolicy']['tpEndDate'];
            $tp_policy_no       = $proposal_addtional_details['prepolicy']['tpInsuranceNumber'];

            $tp_insurer_address = DB::table('insurer_address')->where('Insurer', $tp_insurer_name)->first();
            $tp_insurer_address = keysToLower($tp_insurer_address);
        }

        $RegistrationNo_4       = $proposal_addtional_details['vehicle']['regNo3'];

        $proposal_addtional_details['vehicle']['regNo3'] = ((strlen($RegistrationNo_4) == 1) ? '000' . $RegistrationNo_4 : ((strlen($RegistrationNo_4) == 2) ? '00' . $RegistrationNo_4 : ((strlen($RegistrationNo_4) == 3) ? '0' . $RegistrationNo_4 : $RegistrationNo_4)));

        // addon
        $is_aa_apllicable = false;

        $insurer = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();
        $insurer = keysToLower($insurer);

        $proposal_salutation    = ($is_individual ? (($proposal->gender == 'M') ? '1' : (($proposal->marital_status == 'M') ? '4' : '2')) : '_');

        $proposal_date          = date('d/m/Y');

        $registration_year      = Carbon::parse($motor_manf_date)->format('Y');
        $registration_date      = strtotime($requestData->vehicle_register_date) . '000';

        $policy_date['start']   = strtotime($policy_start_date);
        $policy_date['end']     = strtotime($policy_end_date);

        $prev_policy['end']     = strtotime($requestData->previous_policy_expiry_date);
        $prev_policy['start']   = strtotime('-1 year -1 day', $prev_policy['end']);

        $tp_policy_date['start_date']      = strtotime('+1 day', $policy_date['start']);
        $tp_policy_date['end_date']        = strtotime('+3 year -1 day', $tp_policy_date['start_date']);

        $yn_claim           = 'Y';
        $ncb['current']     = 0;
        $ncb['applicable']  = 0;
        $ncb['active']      = false;
        $ncb['level']       = 0;

        $ncb_levels = ['0' => '0', '20' => '1', '25' => '2', '35' => '3', '45' => '4', '50' => '5'];

        if ($requestData->is_claim == 'N') {
            $yn_claim           = 'N';
            $ncb['active']      = true;
            $ncb['current']     = $requestData->previous_ncb;
            $ncb['applicable']  = $requestData->applicable_ncb;
            $ncb['level']       = $ncb_levels[$ncb['applicable']];
        }


        $pincode_data = NicSubmitProposal::get_state_city_from_pincode($proposal->pincode);

        $customer = [
            'type' => ($is_individual ? 'Individual' : 'Corporate'),
            'first_name' => $proposal->first_name,
            'last_name' => $proposal->last_name,
            'email' => $proposal->email,
            'mobile' => $proposal->mobile_number,
            'marital_status' => ($proposal->marital_status),
            'gender' => ($proposal->gender),
            'dob' => strtotime($proposal->dob),
            'occupation' => $proposal->occupation,
            'address' => ($proposal->address_line1 . ' ' . $proposal->address_line2 . ' ' . $proposal->address_line3),
            'pincode' => $proposal->pincode,
            'state' => $proposal->state,
            'city' => $proposal->city,
            'pan_no' => $proposal->pan_number,
            'gstin' => $proposal->gst_number,
            'salutation' => $proposal_salutation,
            'pincode_details' => $pincode_data,
            'quote' => $enquiryId,
            'section' => 'cv',
            'method' => 'Customer_creation - Proposal',
        ];


        $customer_data = NicSubmitProposal::create_customer($customer, $enquiryId, $productData);

        if ($customer_data['status'] == false) {
            return $customer_data;
        } else {
            $customer_id = $customer_data['customer_id'];
        }
// dd($customer_id);
        $planId = ($is_new
            ? ($is_package
                ? 700015914
                : 700015908)
            : ($is_package
                ? 100000241
                : ($is_liability
                    ? 100000242
                    : 700015982)
            )
        );

        $quote_request_data['od_package'] = self::create_od_request($policy_date, $is_applicable, $registration_year, $is_new, $mmv_data, $is_liability);

        $cover_details = [
            'is_cpa' => [
                'is_applicable' => true, //$is_cpa,
            ],
            'is_pa_paid_driver' => [
                'is_applicable' => $is_pa_paid_driver,
                'si' => $PAforaddionaldPaidDriverSI,
            ],
            'pa_named' => [
                'is_applicable' => $pa_named,
            ],
            'is_pa_unnamed' => [
                'is_applicable' => $is_pa_unnamed,
                'si' => $PAforUnnamedPassengerSI,
            ],
        ];




        $quote_request_data['pa'] = self::create_pa_request($policy_date, $cover_details, $is_new, $mmv_data);



        $quote_request_data['mmv'] = self::create_mmv_details_request($is_applicable, $addon, $mmv, $rto_data, $registration_year, $motor_manf_date, $is_new);

        $quote_request_data['legal_liability'] = self::create_legal_liability_tp_request($policy_date, $is_applicable, $is_new);


        // END ADDON REQUEST

        $quote_request_data['invoice']          = self::create_invoice_request($policy_date, $mmv_data, $is_new);
        $quote_request_data['rsa']              = self::create_rsa_request($policy_date, $mmv_data, $is_new);
        $quote_request_data['engine_protect']   = self::create_engine_protect_request($policy_date, $registration_date, $mmv_data, $is_new);

        // END ADDON REQUEST

        $quote_request_data['discount'] = self::create_od_discount_request($is_liability);

        $quote_request_data['customer'] = [

            'addressLine'   => $proposal->address_line1 . ', ' . $proposal->address_line2,
            'addressType'   => '2',
            'city'          => '641001',//$pincode_data['data']['city_id'],
            'country'       => '91',
            'dateOfBirth'   => (int)($is_individual ? strtotime($proposal->dob) . '000' : ''),
            'district'      => '43003',//$pincode_data['data']['district_id'],
            'email'         => $proposal->email,
            'field02'       => '1',
            'field10'       => '05-03-1995',
            'firstName'     => $proposal->first_name,
            'gender'        => ($is_individual ? ($proposal->gender == 'M' ? '1' : '2') : ''),
            'lastName'      => $proposal->last_name,
            'mobile'        => $proposal->mobile_number,
            'occupation'    => '9961',//($is_individual ? $proposal->occupation : ''),
            'policyId'      => '',
            // 'maritalStatus' => ($is_individual ? ($proposal->marital_status == 'Single' ? '1' : '2') : ''),
            // 'language'      => '1010',
            'postCode'      => '112134',//(string)($proposal->pincode),
            'state'         => '43',//$pincode_data['data']['state_id'],
            'title'         => '3',//$proposal_salutation,
            // 'field01'       => $proposal->pan_number ?? '',
            // 'field05'       => 'N',
        ];

        $quote_request_data['prev_policy'] = [
            0 => [
                'claimIncurredRatio'    => '',//($is_new ? '' : ($yn_claim == 'N' ? 0 : 1)),
                'policyFrom'            => ($is_new ? '' : $prev_policy['start'] . '000'),
                'companyBranch'         => ($is_new ? '' : 846108),
                'premiumPaid'           => ($is_new ? '' : '23000'),
                'listId'                => '',
                'policyId'              => '',
                'policyNo'              => '776767567',//($is_new ? '' : $proposal->previous_policy_number),
                'policyTo'              => ($is_new ? '' : $prev_policy['end'] . '000'),
                'noClaims'              => ($is_new ? '' : ($yn_claim == 'N' ? 0 : 1)),
                'prevCompanyName'       => 845016,//($is_new ? '' : $previousInsurerList->code),
                'totClaimsIncurr'       => ($is_new ? '' : ($yn_claim == 'N' ? "0" : "1")),
            ],
        ];

        $quote_request_data['vehicle'] = [
            'chasisNumber' => $proposal->chassis_number,
            'engineNumber' => $proposal->engine_number,
            'ncdAmount' => '0',
            'ncdEntitlement' => 0,
            'rejectFlag' => false,
            'roadTax' => 0,
            'numberofSeats' => $mmv->seating_capacity,
            // 'vehicleAge' => floor($age / 12),
            'vehicleRegisterDate' => $registration_date,
            'vehicleRegisterNo' => 'UP-32-EN-4213',//$vehicle_register_no,
            'weight' => 0,
            'claimDescription' => ''
        ];

        $genPolicyInfoSOABO = [
            'fieldValueMap' => [
                'NIC_City_DropList' => '2',
                'NIC_Claims_Ratio_Loading' => '',
                'NIC_CIF_Gross_Premium_of_CIF_Policy' => $mmv->idv,
                'AnyLossOrDamage' => '2',
                'FleetDiscountRate' => '0',
                'NIC_GMCT_TPA_Option' => '2',
                'NIC_Number_Fleet_Vehicles' => '0',
                'NIC_CIF_Sum_Insured' => '',
                'FleetDiscount' => '2',
                'HasErectionStart' => '2',
                'NIC_State_DropList' => '2',
                'NIC_Basis_of_Insurance_Policy_Level' => '1',
                'NIC_Financier_Address_PolicyLevel' => '',
                'NIC_Driver_Clause' => "Any person including insured: Provided that a person driving holds an effective driving license at the time of the accident and is not disqualified from holding or obtaining such a license. Provided also that the person holding an effective Learner's license may also drive the vehicle when not used for the transport of goods at the time of the accident and that such a person satisfies the requirements of Rule 3 of the Central Motor Vehicles Rules, 1989.",
                'NIC_Limitations' => "The Policy covers use only under a permit within the meaning of the Motor Vehicle Act, 1988 or such a carriage falling under Sub-Section 3 of Section 66 of the Motor Vehicle's Act 1988.The Policy does not cover:(1) Use for organized racing, pace-making, reliability trial or speed testing. (2) Use whilst towing any trailer's, except the trailer's insured with the Company, or the towing (other than for reward) of any one disabled mechanically propelled vehicles.(3) Use for carrying passengers in the vehicles except employees (other than the driver) not exceeding the number permitted in the registration document and coming under the purview of Workmen's Compensation Act 1923.",
            ],
            'actualAnnualTurnoverCarrying' => 0,
            'marineServiceTaxFlag' => '0',
            'estimatedAnnualTurnoverCarrying' => 0,
            'ncdAmount' => '0',
            'poiPrintorNot' => 1,
            'policyId' => '',
        ];

        // if (!$is_new) {
        //     $genPolicyInfoSOABO['fieldValueMap']['NIC_Claims_Ratio_Loading']            = '40';
        //     $genPolicyInfoSOABO['fieldValueMap']['NIC_Long_Term_TP_Policy_Start_Date']  = ($is_od ? $tp_policy_date['start_date'] . '000' : '');
        //     $genPolicyInfoSOABO['fieldValueMap']['NIC_Long_Term_TP_Policy_Expiry_Date'] = ($is_od ? $tp_policy_date['end_date'] . '000' : '');
        //     $genPolicyInfoSOABO['fieldValueMap']['NIC_VoluntaryDeductibles']            = '598142';
        //     $genPolicyInfoSOABO['fieldValueMap']['NIC_MCDT_CIF_Policy_No']              = '98987856';
        //     $genPolicyInfoSOABO['fieldValueMap']['NIC_Financier_Policy']                = '598208';
        // }

        // if ($is_od) {
        //     $genPolicyInfoSOABO['fieldValueMap']['NIC_Long_Term_TP_Policy_Start_Date']    = $tp_policy_date['start_date'] . '000';
        //     $genPolicyInfoSOABO['fieldValueMap']['NIC_Long_Term_TP_Policy_Expiry_Date']   = $tp_policy_date['end_date'] . '000';
        // }

        $quote_array = [
            'userName' => config('constants.IcConstants.nic.USERNAME_NIC_CV_MOTOR'),
            'branchId' => config('constants.IcConstants.nic.OFFICE_CODE_NIC_CV_MOTOR'),
            'customerId' => $customer_id,
            'customerName' => $customer['first_name'].' '.$customer['last_name'],
            'customerType' => ($is_individual ? 'INDIVIDUAL' : 'CORPORATE'),
            'from' => config('constants.IcConstants.nic.FROM_NIC_CV_MOTOR'),
            'textAttribute1' => '2',
            'policySOABO' => [
                'dynamicObjectList' => [
                    0 => [
                        'bizTableName' => 'MCOC_MASTER_POLICY',
                        'dynamicAttributeVOList' => [
                            0 => [
                                'valueMap' => [
                                    'Import' => '',
                                    'POLICYID' => '',
                                    'FromPlace' => '',
                                    'DYN_DATA_ID' => '',
                                    'ToPlace' => '',
                                    'ModeofTransport' => '',
                                    'Export' => '0',
                                ],
                            ],
                        ],
                    ],
                    1 => [
                        'bizTableName' => 'NIC_FINANCIER_POLICY',
                        'dynamicAttributeVOList' => [
                            0 => [
                                'valueMap' => [
                                    'FinancierAddress' => 'sdffd',
                                    'POLICYID' => '',
                                    'FinancierName' => 'test',
                                    'PartyID' => '',
                                    'DYN_DATA_ID' => '',
                                    'Party_ID' => '',
                                    'AgreementType' => '1',
                                ],
                            ],
                        ],
                    ],
                ],
                'agreementCode' => config('constants.IcConstants.nic.AGREEMENT_CODE_NIC_CV_MOTOR'),
                'effectDate' => $policy_date['start'] . '000',
                'expiryDate' => $policy_date['end'] . '000',
                'policyId' => '',
                'productCode' => $parent_code ?? '',
                'productId' => ($parent_code == 'GCV') ? 700000060 : 700000002,
                'productName' => ($parent_code == 'GCV') ? 'Goods Carrying Vehicle' : 'Motor - Passenger Carrying Vehicle',
                'ncdLevel' => '',
                'quotationNumber' => '',
                'proposalStatus' => '',
                'genPolicyInfoSOABO' => $genPolicyInfoSOABO,
                'insuredSOABOList' => [
                    0 => [
                        'fieldValueMap' => $quote_request_data['mmv'],
                        'dynamicObjectList' => [
                            0 => [
                                'bizTableName' => 'NIC_Motor_Geographical',
                                'dynamicAttributeVOList' => [
                                    0 => [
                                        'valueMap' => [
                                            'NICMotorGEO07' => '',
                                            'NICMotorGEO06' => '2',
                                            'INSUREDID' => '',
                                            'NICMotorGEO05' => '2',
                                            'NICMotorGEO04' => '2',
                                            'NICMotorGEO' => '',
                                            'NICMotorGEO03' => '2',
                                            'DYN_DATA_ID' => '',
                                            'NICMotorGEO02' => '2',
                                            'NICMotorGEO01' => '2',
                                        ],
                                    ],
                                ],
                            ],
                            1 => [
                                'bizTableName' => 'NICMotorDriverInfo',
                                'dynamicAttributeVOList' => [
                                    0 => [
                                        'valueMap' => [
                                            'Detailsofdeformity' => '',
                                            'Age' => '',
                                            'ExpiryDateOfPSV' => '',
                                            'DriversEducationalQualifications' => '',
                                            'DateofBirth' => '',
                                            'Hasthedriverever' => '',
                                            'DYN_DATA_ID' => '',
                                            'DateofAccident' => '',
                                            'NICMotorDriverLicenseType02' => '',
                                            'NICMotorDriverLicenseType03' => '',
                                            'Dateofexpiryoflicense' => 1672338600000,
                                            'NICMotorDriverLicenseType04' => '',
                                            'NICMotorDriverLicenseType05' => '',
                                            'PSVBadgeNumber' => '',
                                            'NICMotorDriverLicenseType06' => '',
                                            'NICMotorDriverLicenseType07' => '',
                                            'NICMotorDriverLicenseType08' => '',
                                            'LossCostInRupees' => '',
                                            'NICMotorDriverLicenseType09' => '',
                                            'Licencenumber' => '3243455677',
                                            'Licencetype' => '',
                                            'DriversExperience' => '',
                                            'MaritalStatus' => '',
                                            'NICMotorDriverLicenseType01' => '',
                                            'VehicleDrivenBy' => '',
                                            'AnyOtherRelevantInformation' => '',
                                            'IssuingAuthority' => '',
                                            'INSUREDID' => '',
                                            'NICMotorDriverLicenseType10' => '',
                                            'HazardousExplosiveslicenseEndt' => '',
                                            'Address' => '',
                                            'Gender' => '',
                                            'Nameofthedriver' => '',
                                            'Doesthedriversuffer' => '',
                                            'ExpiryDateofHazardousExplosiveslicenseEndt' => ''
                                        ],
                                    ],
                                ],
                            ],
                            2 => [
                                'bizTableName' => 'TrailerInfo',
                                'dynamicAttributeVOList' => [
                                    0 => [
                                        'valueMap' => [
                                            'INSUREDID' => '',
                                            'TrailerBodyType' => '',
                                            'RegistrationNumber' => '',
                                            'IDV' => '',
                                            'SellingPrice' => '',
                                            'DateOfManufacture' => '',
                                            'DYN_DATA_ID' => '',
                                            'ChasisNumber' => '',
                                            'Make' => '',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'effectiveDate' => (int)$policy_date['start'] . '000',
                        'expiryDate' => (int)$policy_date['end'] . '000',
                        'insuredCategory' => 1,
                        'insuredCategoryCode' => 'Vehicle Insured',
                        'insuredId' => '',
                        'insuredName' => 'Virtual Insured',
                        'planId' => '700000122',$planId,
                        'policyId' => '',
                        "deletedPolicyCtSOABOList" => [],
                        'policyCtSOABOList' => [],
                        'vehicleInsuredSOABO' => $quote_request_data['vehicle'],
                    ],
                ],
                'policyPaymentSOABO' => [
                    'policyPayInfoSOABOList' => [
                        0 => [
                            'payInfoId' => '',
                            'payMode' => 101,
                            'policyId' => '',
                        ],
                    ],
                ],
                'nicPolicyCustSOABO' => $quote_request_data['customer'],
                'previousInsuranceSOABOList' => $quote_request_data['prev_policy'],
                'policyDiscSOABOList' => [],//$quote_request_data['discount'],
                'deletedPolicyDiscSOABOList' => [],
            ],
        ];

        $quote_array['policySOABO']['insuredSOABOList'][0]['fieldValueMap'] = $quote_request_data['mmv'];

        $idv_quote_array = $quote_array;

        if (!$is_od) {
            array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['legal_liability']);
        }

        if (!$is_od && ($is_cpa || $pa_named || $is_pa_paid_driver || $is_pa_unnamed)) {
            array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['pa']);
        }

        if (!$is_liability) {
            array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['od_package']);
        }

        if ($addon['rsa']) {
            array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['rsa']);
        }

        if ($addon['rti']) {
            array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['invoice']);
        }

        if ($addon['engine_protect']) {
            array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['engine_protect']);
        }

        // quick quote service input

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Proposal Submition - Proposal',
            'transaction_type'  => 'proposal',
            'productName'       => $productData->product_name,
            'content_type'      => 'text/plain',
            'WWW-Authenticate'  => config('constants.IcConstants.nic.AUTH_KEY_NIC_MOTOR')
        ];

        // return $quote_array;
// dd($quote_array);
        $get_response = getWsData(config('constants.IcConstants.nic.END_POINT_URL_NIC_CV_MOTOR_PREMIUM'), $quote_array, 'nic', $additional_data);
// dd($get_response);
        $response = $get_response['response'];
        $xhdf = ($response) ? 'true' : 'false';

        if ($response) {
            $response = json_decode($response, true);

            if ($response['responseCode'] != '999') {
                return [
                    'status'    => false,
                    'msg'       => $response['responseMessage'],
                    'message'   => $response['responseMessage']
                ];
            }

            $anti_theft_discount    = '0';
            $bonus_discount         = '0';
            $liability              = ($is_applicable['legal_liability'] ? 50 : 0);
            $electrical_amount      = '0';
            $non_electrical_amount  = '0';
            $lpg_cng_amount         = '0';
            $lpg_cng_tp_amount      = '0';

            $pa_unnamed             = ($is_pa_unnamed
                ? (
                    ($mmv->seating_capacity - 1)
                    * (
                        ($PAforUnnamedPassengerSI == '50000')
                        ? 25
                        : (
                            ($PAforUnnamedPassengerSI == '100000')
                            ? 50
                            : (
                                ($PAforUnnamedPassengerSI == '200000')
                                ? 100
                                : 0
                            )
                        )
                    )
                )
                : '0'
            );

            $pa_paid_driver         = (($is_pa_paid_driver) ? (($PAforaddionaldPaidDriverSI == '100000') ? '50' : '100') : '0');
            $tax = 0;

            if (isset($response['premiumBifurcation']['IGST'])) {
                $tax = $response['premiumBifurcation']['IGST'];
            } else {
                $tax = $response['premiumBifurcation']['CGST'] + $response['premiumBifurcation']['SGST-UTGST'];
            }
            // $tax = $response['premiumBifurcation']['CGST'];

            $cover_codes = [
                '700000500' => 'od',
                '700001243' => 'tp',
                '700001320' => 'pa',

                '700021420' => 'return_to_invoice',
                '700022514' => 'rsa',
                '700022409' => 'zero_dep',
                '700021865' => 'engine_protect',
            ];

            $covers = [
                'rsa'               => 0,
                'od'                => 0,
                'return_to_invoice' => 0,
                'pa'                => 0,
                'rsa'               => 0,
                'tp'                => 0,
                'engine_protect'    => 0,
                'zero_dep'          => 0,
                'zero_dep_plus'     => 0,
            ];

            foreach ($cover_codes as $key => $value) {
                if (isset($response['quotationEditIds']['coverPremium_' . $key])) {
                    $covers[$value] = (int)$response['quotationEditIds']['coverPremium_' . $key];
                }
            }

            $covers['ods'] = $covers['od'];

            if (!$is_liability && $ncb['active']) {
                $covers['od'] = ($covers['od'] * 100 / (100 - $ncb['applicable']));
            } else {
                $covers['ncb'] = '0';
            }

            $covers['ncb'] = $covers['od'] * $ncb['applicable'] / 100;
            $covers['pa']  = ($covers['pa'] - $pa_unnamed);
            $covers['pa']  = ($covers['pa'] - $pa_paid_driver);

            $covers['tp']  = ($covers['tp'] - $liability);

            $premium['od']['total']         = isset($response['premiumBifurcation']['motorOD']) ? $response['premiumBifurcation']['motorOD'] : '0';
            $premium['tp']['total']         = isset($response['premiumBifurcation']['motorTP']) ? $response['premiumBifurcation']['motorTP'] : '0';
            $premium['premium']['tax']      = $tax;
            $premium['premium']['total']    = $response['premiumBifurcation']['premiumWithOutTax'];
            $premium['premium']['payble']   = $response['premiumBifurcation']['actualPremiumPayable'];

            $total_od_premium           = $covers['od'];
            $total_add_ons_premium      = $covers['rsa'] + $covers['engine_protect'] + $covers['zero_dep'] + $covers['return_to_invoice'];

            $total_tp_premium           = $covers['tp'] + $liability + $pa_paid_driver + $pa_unnamed;

            $total_discount_premium     = $covers['ncb'];

            $proposal->proposal_no = $response['quotationNo'];

            $proposal_addtional_details['nic']['transaction_id']     = $response['quotationNo'];
            $proposal_addtional_details['nic']['customer_id']     = $customer_id;

            $proposal_addtional_details['nic']['vahan']['status']     = $response['vahanStatus'];
            $proposal_addtional_details['nic']['vahan']['response']     = $response['vahanResponseXML'];

            $proposal_addtional_details['nic']['uw']     = $response['autoUwMsg'];


            $final_total_discount               = $total_discount_premium;
            $proposal->final_payable_amount     = $premium['premium']['payble'];
            $proposal->policy_start_date        = Carbon::parse($policy_start_date)->format('d-m-Y');
            $proposal->policy_end_date          = Carbon::parse($policy_end_date)->format('d-m-Y');
            $proposal->od_premium               = $total_od_premium;
            $proposal->tp_premium               = $total_tp_premium;
            $proposal->cpa_premium              = $covers['pa'];
            $proposal->addon_premium            = $total_add_ons_premium;
            $proposal->ncb_discount             = $covers['ncb'];
            $proposal->service_tax_amount       = $premium['premium']['tax'];
            $proposal->total_premium            = $premium['premium']['total'];
            $proposal->discount_percent         = ($is_liability ? 0 : $requestData->applicable_ncb);

            $proposal->additional_details       = $proposal_addtional_details;
            $proposal->save();

            $data['user_product_journey_id']    = customDecrypt($request['userProductJourneyId']);
            $data['ic_id']                      = $productData->policy_id;
            $data['stage']                      = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $data['proposal_id']                = $proposal->user_proposal_id;

            updateJourneyStage($data);
            return response()->json([
                'status' => true,
                'msg' => 'Proposal Submited Successfully..!',
                'data' => camelCase([
                    'proposal_no' => $proposal->proposal_no,
                    'data' => $proposal,
                    'premium_amount' => $proposal->final_payable_amount
                ]),
                'premium_data' => [
                    'base_covers'               => $covers,
                    'premium'                   => $premium,
                    'quotationEditIds'          => $response['quotationEditIds'],
                    'covers'                    => [
                        'anti_theft_discount'       => $anti_theft_discount,
                        'liability'                 => $liability,
                        'electrical_amount'         => $electrical_amount,
                        'non_electrical_amount'     => $non_electrical_amount,
                        'lpg_cng_amount'            => $lpg_cng_amount,
                        'lpg_cng_tp_amount'         => $lpg_cng_tp_amount,
                    ],
                    'pa_cover_details' => $cover_details,
                    'idv' => $mmv->idv
                ]
            ]);
        } else {
            return [
                'status'    => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message'   => 'Insurer Not Reachable'
            ];
        }
    }

    public static function nic_cover_addon_values($value_arr, $cover_codes, $match_arr, $covers)
    {
        if (!isset($value_arr[0])) {
            $value = $value_arr;
            foreach ($cover_codes as $k => $v) {
                if ($value[$match_arr['name']] == $v) {
                    $covers[$k] = (int)$value[$match_arr['value']];
                }
            }
        } else {
            foreach ($value_arr as $key => $value) {
                foreach ($cover_codes as $k => $v) {
                    if ($value[$match_arr['name']] == $v) {
                        $covers[$k] = (int)$value[$match_arr['value']];
                    }
                }
            }
        }
        return $covers;
    }
    public static function create_customer($customer, $enquiryId, $productData)
    {

        $is_individual = ($customer['type'] == 'Individual') ? true : false;

        $name = explode(' ', $customer['first_name']);
        $name[1] = (isset($name[1]) ? $name[1] : '');

        $createCustomerRequest = [
            "address"               =>  $customer['address'],
            "customerType"          =>  $customer['type'],
            "dateOfBirth"           => ($is_individual ? date("d-m-Y", $customer['dob']) : ''),
            "gender"                =>  $customer['gender'],
            "mobileNo"              =>  $customer['mobile'],
            "paidupCapital"         => ($is_individual ? '' : '500000000'),
            "primaryEmail"          =>  $customer['email'],
            "title"                 =>  $customer['salutation'],
            "pan"                   =>  $customer['pan_no'],
            "gstin"                 =>  $customer['gstin'],

            "pinCd"                 =>  '112134',//$customer['pincode_details']['data']['id'],
            "Pin_Sl_No"             =>  '112134',//$customer['pincode_details']['data']['id'],
            "stateName"             =>  '43',//$customer['pincode_details']['data']['state_id'],
            "cityName"              =>  '641001',//$customer['pincode_details']['data']['city_id'],
            "districtName"          =>  'district_id',$customer['pincode_details']['data']['district_id'],

            "occupation"            =>  $customer['occupation'],
            "maritalStatus"         =>  $customer['marital_status'],

            "corporateName"         => (!$is_individual ? $customer['last_name'] : ''),
            "industryType"          => ($is_individual ? '' : '19211901'),
            "organizationType"      => ($is_individual ? '' : '11'),

            'firstName'         => ($is_individual ? $customer['first_name'] : $name[0]),
            'lastName'          => ($is_individual ? $customer['last_name'] : $name[1]),

            "mobileNo2"                   =>  "",
            "faxNumber"                   =>  "",
            "faxSTDCode"                  =>  "",
            "middleName"                  =>  "",
            "aadharNumber"                =>  "",
            "contactPerson"               =>  "",
            //"telephoneNumber"             =>  "",
            "registrationNumber"          =>  "",
            "registrationExpiryDate"      =>  "",
            "registrationAuthority"       =>  "",
            "dateOfRegistration"          =>  "",
            "telephoneSTDCode"            =>  "",
            "secondaryEmail"              =>  "",
            "segmentType"                 =>  "",
            "territory"                   =>  "",
            "language"                    =>  "",
            "website"                     =>  "",
            "zipCode"                     =>  "",
            "sector"                      =>  "",

            "beatCode"                    =>  "100764",
            "partyStatus"                 =>  "1",
            "annualIncome"                =>  "500000",
            "isBankAvailable"             =>  "NO",
            "physicallyChallenged"        =>  "2",

            "addressType"                 =>  "2",
            "country"                     =>  "91",
        ];

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Customer Creation - Proposal',
            'transaction_type'  => 'proposal',
            'productName'       => $productData->product_name,
            'content_type'      => 'text/plain',
            'WWW-Authenticate'  => config('constants.IcConstants.nic.AUTH_KEY_NIC_MOTOR')
        ];

        $get_response = getWsData(config('constants.IcConstants.nic.END_POINT_URL_NIC_CV_CUSTOMER_CREATION'), $createCustomerRequest, 'nic', $additional_data);
// dd($get_response);
        $createCustomerResponse = $get_response['response'];
        if ($createCustomerResponse) {
            $str["from"]   = strpos($createCustomerResponse, "[");
            $str["to"]     = strpos($createCustomerResponse, "]");
            $str["rem"]    = $str["to"] - $str["from"];

            $str["customer_id"] = substr($createCustomerResponse, $str["from"] + 1, $str["rem"] - 1);

            if ($str["customer_id"]) {
                return [
                    "status"        => 'true',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    "customer_id"   => $str["customer_id"],
                ];
            } else {
                return [
                    "status"    => 'false',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    "message"   => $createCustomerResponse,
                    "customer_id"   => '',
                ];
            }
        } else {
            return [
                "status"    => 'false',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                "message"   => 'insurer not reachable',
                "customer_id"   => '',
            ];
        }
    }
    public static function get_state_city_from_pincode($pincode)
    {
        $query_data = DB::table('nic_pincode_master as nicpcm')
            ->join('nic_state_master as nicsm', 'nicpcm.state_id', '=', 'nicsm.state_id')
            ->join('nic_district_master as nicdm', 'nicpcm.district_id', '=', 'nicdm.district_id')
            ->where('nicpcm.pin_cd', $pincode)
            ->select('nicsm.state_name as state', 'nicdm.district_name as city', 'nicdm.*', 'nicsm.*', 'nicpcm.*')
            ->first();

        if ($query_data) {
            return [
                'status' => true,
                'state_name'    => $query_data->state,
                'city_name'     => $query_data->city,
                'data'          => (array)$query_data,
            ];
        } else {
            return ['status' => false];
        }
    }





    public static function create_pa_request($policy_date, $pa_cover_details, $is_new, $mmv_data)
    {
        // dd('jj');
        $pa_request = [
            "coverTypeId" => 700001440,
            "coverTypeName" => "Personal Accident",
            "deletedPolicyCtAcceBOList" => [],
            'effectiveDate' => $policy_date['start'] . '000',
            'expirtyDate' => $policy_date['end'] . '000',
            "insuredId" => "",
            "policyCtId" => "",
            "siOfCoverType" => 0,
        ];
        // dd($pa_request);
        if ($is_new) {
            // $pa_request['fieldValueMap'] = [
            //     'NIC_Premium3' => '433.3333333333333',
            //     'NIC_TEA_VSL_AGE' => '433.3333333333333',
            //     'NIC_TEA_VSL_EXTRA_SI' => '433.3333333333333'
            // ];
        }

        if ($pa_cover_details['is_cpa']['is_applicable']) {
            $pa_request['policyCtAcceSOABOList'] = [];
            array_push(
                $pa_request['policyCtAcceSOABOList'],
                [
                    'fieldValueMap' => [
                        'NICBenefitNomineeName' => 'test',
                        'NICBenefitNomineeAge' => '24',
                        'NICBennefitRelationship' => 'brother',
                    ],
                    'interestTypeCode' => 'Compulsory PA',
                    "interestName" => "",
                    'interestSi' => 1500000,
                    'interestType' => 700000300,
                    'policyCtId' => '',
                    'AdditionalDesc' => '',
                    'InterestDescription' => '',
                ]
            );
        }
        // dd('jj');
        if ($pa_cover_details['is_pa_paid_driver']['is_applicable']) {
            $pa_request['policyCtAcceSOABOList'] = [];
            array_push(
                $pa_request['policyCtAcceSOABOList'],
                [
                    'interestTypeCode' => 'Optional PA for Paid Driver/Cleaner/Conductor',
                    'interestSi' => $pa_cover_details['is_pa_paid_driver']['si'],
                    'interestType' => 700000424,
                    'policyCtId' => '',
                    'interestName' => 'akedghs',
                    // 'fieldValueMap' => [
                    //     'NIC_PC_Interest_Age' => '29',
                    //     'NICBenefitNomineeAge' => '31',
                    //     'NICBenefitNomineeName' => 'uyetruv',
                    //     'NICBennefitRelationship' => 'husband',
                    //     'NICNomineeAddress' => 'rteaddress'
                    // ],
                ]
            );
        }

        if ($pa_cover_details['pa_named']['is_applicable']) {
            array_push(
                $pa_request['policyCtAcceSOABOList'],
                [
                    'fieldValueMap' => [
                        'NICBenefitNomineeName' => 'dbsfuhbgv',
                        'NICBenefitNomineeAge' => '32',
                        'NICBennefitRelationship' => 'husband',
                        'NIC_PC_Interest_Age' => '29',
                        'NICNomineeAddress' => 'dfgdfg',
                    ],
                    'interestTypeCode' => 'PA Named',
                    'interestName' => 'akedghs',
                    'interestSi' => 100000,
                    'interestType' => 700000301,
                    'policyCtId' => '',
                    'AdditionalDesc' => '',
                    'InterestDescription' => '',
                ]
            );
        }

        if ($pa_cover_details['is_pa_unnamed']['is_applicable']) {
            array_push(
                $pa_request['policyCtAcceSOABOList'],
                [
                    'fieldValueMap' => [
                        'NoOfQualifiedPersions' => (string)($mmv_data['seating_capacity'] - 1),
                    ],
                    'interestTypeCode' => 'PA Un-Named',
                    'interestSi' => $pa_cover_details['is_pa_unnamed']['si'],
                    'interestType' => 700000302,
                    'policyCtId' => '',
                ]
            );
        }
        return $pa_request;
    }

    public static function create_legal_liability_tp_request($policy_date, $is_applicable, $is_new)
    {
        // dd('kk');
        $legal_liability_tp_request = [
            'coverTypeName' => 'Legal Liability to Third Party',
            'coverTypeId' => 700006685,
            'effectiveDate' => $policy_date['start'] . '000',
            'expirtyDate' => $policy_date['end'] . '000',
            'insuredId' => '',
            'policyCtId' => '',
            'siOfCoverType' => 9999999999999.99,
            "policyCtAcceSOABOList" => [
                [
                    "fieldValueMap" => [
                        "NIC_DamageThirdParty_Interest" => "1",
                    ],
                    "interestSi" => 0,
                    "interestType" => 100000041,
                    "interestTypeCode" =>
                    "Third Party Property Damage",
                    "policyCtId" => "",
                ],
                [
                    "fieldValueMap" => [
                        "NICBenefitTPBILimit" => "999999999",
                    ],
                    "interestSi" => 0,
                    "interestType" => 100000040,
                    "interestTypeCode" =>
                    "Third Party Bodily Injury",
                    "policyCtId" => "",
                ],
                [
                    "fieldValueMap" => [
                        "NIC_GCV_NO" => "2",
                    ],
                    "interestSi" => 0,
                    "interestType" => 700000422,
                    "interestTypeCode" =>
                    "Legal Liability to Driver, Cleaner, Coolies (Upto 6)",
                    "policyCtId" => "",
                ],
                [
                    "fieldValueMap" => [
                        "NIC_GCV_NO" => "2",
                    ],
                    "interestSi" => 0,
                    "interestType" => 700000420,
                    "interestTypeCode" =>
                    "LL to Non Fare Paying Passengers Employees (not workmen under WC act)",
                    "policyCtId" => "",
                ],
                [
                    "fieldValueMap" => [
                        "NIC_GCV_NO" => "1",
                    ],
                    "interestSi" => 0,
                    "interestType" => 700000421,
                    "interestTypeCode" =>
                    "LL to Non Fare Paying Passengers owners of goods (excluding employees)",
                    "policyCtId" => "",
                ],
            ],
        ];

        if ($is_new) {
            // $legal_liability_tp_request['fieldValueMap'] = [
            //     'NIC_Premium3'          => '0',
            //     'NIC_TEA_VSL_AGE'       => '0',
            //     'NIC_TEA_VSL_EXTRA_SI'  => '0'
            // ];
        }
        // dd($is_applicable['legal_liability']);
        if ($is_applicable['legal_liability']) {
            array_push(
                $legal_liability_tp_request['policyCtAcceSOABOList'],
                [
                    'fieldValueMap' => [
                        'NICBenefitPaidDriver' => '1',
                    ],
                    'interestTypeCode' => 'Legal Liability to Paid Driver/Cleaner',
                    'interestSi' => 0,
                    'interestType' => 700000304,
                    'policyCtId' => '',
                ]
            );
        }
        return $legal_liability_tp_request;
    }


    public static function create_od_request($policy_date, $is_applicable, $registration_year, $is_new, $mmv_data, $is_liability)
    {
        // OD PACKAGE BLOCK
        $od_package['od'] = [
            'fieldValueMap' => [
                'IManufacturerSellingPrice' => '735832',
                'NIC_Limit' => '40',
            ],
            'interestTypeCode' => 'Own Damage',
            'interestSi' => $mmv_data['idv'],
            'interestType' => 700000120,
            'policyCtId' => '',
        ];

        if ($is_new) {
            $od_package['od']['fieldValueMap'] = [
                'NIC_Limit' => '40',
                'NIC_Loading6' => '23363',
                'NIC_Loading5' => $mmv_data['idv3'],
                'NIC_EscalationAmount' => '31707',
                'NIC_LoadingA' => $mmv_data['idv2'],
                'IManufacturerSellingPrice' => $mmv_data['idv'],
                'NIC_RISK_CODE_Benefit' => '26701',
                'NIC_FestivalSumInsured_Benefit' => $mmv_data['idv'],
            ];
        }

        // $od_package['electrical_accessories'] = [
        //     'fieldValueMap' => [
        //         'NIC_Loading6' => '0',
        //         'NIC_Loading5' => '0',
        //         'NIC_EscalationAmount' => '0',
        //         'NIC_LoadingA' => '0',
        //         'IManufacturerSellingPrice' => '1000',
        //         'YearOfMake' => $registration_year,
        //         'NIC_RISK_CODE_Benefit' => '0',
        //         'NIC_FestivalSumInsured_Benefit' => '0',
        //         'NIC_Depriciation1' => '1',
        //     ],
        //     'interestTypeCode' => 'Electrical Accessories',
        //     'interestDescription' => 'jvhb',
        //     'interestSi' => 950,
        //     'interestType' => 100000060,
        //     'policyCtId' => '',
        // ];

        // $od_package['non_electrical_accessories'] = [
        //     'fieldValueMap' => [
        //         'NIC_Loading6' => '0',
        //         'NIC_Loading5' => '0',
        //         'NIC_EscalationAmount' => '0',
        //         'NIC_LoadingA' => '0',
        //         'IManufacturerSellingPrice' => '1000',
        //         'YearOfMake' => $registration_year,
        //         'NIC_RISK_CODE_Benefit' => '0',
        //         'NIC_Depreciation2' => '1',
        //         'NIC_FestivalSumInsured_Benefit' => '0',
        //     ],
        //     'interestTypeCode' => 'Non Electrical Accessories',
        //     'interestDescription' => 'gfd',
        //     'interestSi' => 950,
        //     'interestType' => 100000061,
        //     'policyCtId' => '',
        // ];

        // $od_package['cng'] = [
        //     'fieldValueMap' => [
        //         'NIC_Loading6' => '0',
        //         'NIC_Loading5' => '0',
        //         'NIC_EscalationAmount' => '0',
        //         'NIC_LoadingA' => '0',
        //         'IManufacturerSellingPrice' => '500',
        //         'YearOfMake' => $registration_year,
        //         'NIC_RISK_CODE_Benefit' => '0',
        //         'NIC_Depreciation3' => '1',
        //         'NIC_FestivalSumInsured_Benefit' => '0',
        //     ],
        //     'interestTypeCode' => 'CNG Kit',
        //     'interestDescription' => 'etfsf',
        //     'interestSi' => 475,
        //     'interestType' => 700000100,
        //     'policyCtId' => '',
        // ];

        // $od_package['fiber_glass_tank'] = [
        //     'fieldValueMap' => [
        //         'NIC_Loading6' => '0',
        //         'NIC_Loading5' => '0',
        //         'NIC_EscalationAmount' => '0',
        //         'NIC_LoadingA' => '0',
        //         'IManufacturerSellingPrice' => '1500',
        //         'YearOfMake' => $registration_year,
        //         'NIC_Depreciation5' => '1',
        //         'NIC_RISK_CODE_Benefit' => '0',
        //         'NIC_FestivalSumInsured_Benefit' => '0',
        //     ],
        //     'interestTypeCode' => 'Fiber Glass Tank',
        //     'interestDescription' => 'fdghfh',
        //     'interestSi' => 1425,
        //     'coverTypeId' => 700000102,
        //     'policyCtId' => '',
        // ];

        $od_request = [
            'coverTypeName' => 'Own Damage Package',
            'coverTypeId' => 700000500,
            'effectiveDate' => $policy_date['start'] . '000',
            'expirtyDate' => $policy_date['end'] . '000',
            'insuredId' => '',
            'policyCtId' => '',
            'siOfCoverType' => 1500000,
            'policyCtAcceSOABOList' => [],
        ];

        if ($is_new) {
            $od_request['fieldValueMap'] = [
                'NIC_TEA_VSL_EXTRA_SI' => '26878',
                'NIC_ExchangeRate' => '1023488',
                'NIC_TEA_VSL_AGE' => '23525',
                'NIC_Deductible3' => '1215392',
                'NIC_Supplier_Customer' => '895552',
                'NIC_Premium3' => '31909'
            ];
        }

        if (!$is_liability) {
            array_push($od_request['policyCtAcceSOABOList'], $od_package['od']);
            // array_push($od_request['policyCtAcceSOABOList'], $od_package['fiber_glass_tank']);

            // if ($is_applicable['motor_electric_accessories']) {
            //     array_push($od_request['policyCtAcceSOABOList'], $od_package['electrical_accessories']);
            // }

            // if ($is_applicable['motor_non_electric_accessories']) {
            //     array_push($od_request['policyCtAcceSOABOList'], $od_package['non_electrical_accessories']);
            // }

            // if ($is_applicable['motor_lpg_cng_kit']) {
            //     array_push($od_request['policyCtAcceSOABOList'], $od_package['cng']);
            // }
        }
        return $od_request;
    }


    public static function create_mmv_details_request($is_applicable, $addon, $mmv, $rto_data, $registration_year, $motor_manf_date, $is_new)
    {
        // dd($motor_manf_date);
        $fuel = $mmv->nic_fuel_type_code;

        $mmv_details_request = [
            'NIC_Driver'                    => '2',
            'NIC_Unladen_Weight'            => $mmv->weight,
            'NICVehicleMake'                => $mmv->nic_make_code,
            'TypeOfFuel'                    => $fuel,
            'NIC_Indemnity_to_Hirer'        => '2',
            'InsuredTowingCharges'          => '0',
            'NIC_Second_Hand_Vehicle'       => '2',
            'NIC_Vehicle_Age'               => '0',
            'VehColor'                      => '106090205',
            'NIC_Class_of_Vehicle'          => '7',
            'VehicleCategory'               => $is_new ? '1' : '2',
            'RegisteringAuthorityCodeName'  => '16032',
            'Zone'                          => 'B',
            'NIC_Number_of_Trailers'        => '0',
            'InsuredGoodFeatureDiscountA'   => '0',
            'NIC_TP_Loading'                => '0',
            'InsuredGoodFeatureDiscountC'   => '0',
            'InsuredGoodFeatureDiscountB'   => '0',
            'NIC_Handicapped'               => '2',
            'DrivingTuitions'               => '2',
            'NIC_GCV_CNG_LPG'               => '2',
            'NIC_Vehicle_Commercial'        => '2',
            'NIC_Coverage_IMT_23'           => '2',
            'InsuredCNGInbuilt'             => ($is_applicable['motor_lpg_cng_kit'] ? '1' : '2'),
            'InsuredCompulsoryExcess'       => '',
            'NIC_Gross_Vehicle_Weight'      => '234',
            'NIC_Nature_of_Goods'           => '0',
            'NIC_GCV_Permit_Type'           => '1',
            'LimitedToOwnPremises'          => '2',
            'NIC_GCV_Type_of_Body'          => '2',
            'TotalIDV'                      => '0',
            'ODLoading'                     => '0',
            'DateOfDeliveryPurchase'        => strtotime($motor_manf_date) . '000',
            'ObsoleteVehicle'               => '2',
            'NIC_Use_Confined_to_Site'      => '2',
            'NICVehicleModel'               => $mmv->nic_model_code,
            'NIC_GCV_Variant'               => $mmv->nic_variant_code,
            'YearOfManufacture'             => strtotime($motor_manf_date) . '000',
            'NIC_Gross_Laden_Weight'        => '100',
            'AntiTheftDevice'               => ($is_applicable['motor_anti_theft'] ? '1' : '2'),
            //
            // 'NIC_PC_Cubic_Capacity'         => $mmv->cubic_capacity,
            // 'NIC_FullRiskDesc'              => ($addon['zero_dep'] ? '1' : '2'),
            // 'NIC_CNGLPG'                    => (($fuel == '5') ? '1' : '2'),
            // 'RegisteringAuthorityCodeName'  => $rto_data->easi_rto,
            // 'NICInsuredTPLoadingPercent'    => '0',
            // 'Handicapped'                   => '2',
            // 'ThreeWheeler'                  => '2',
            // 'VintageCar'                    => '2',
            // 'NIC_GeoState_Insured'          => '5',
            // 'NIC_Side_Car_IDV'              => '40',
            // 'InsuredGoodFeatureDiscountB'   => '0',
            // 'ClassicCar'                    => '2',
            // 'AutomobileAssociationMembership' => ($is_applicable['automobile_association'] ? '1' : '2'),
            // 'TypeOfBody'                    => '11',
            // 'NIC_PC_NUMBER_TRAILER'         => '0',
            // 'ClassOfVehicle'                => '2',
        ];
        return $mmv_details_request;
    }


    public static function create_invoice_request($policy_date, $mmv_data, $is_new)
    {

        $invoice_request = [
            'fieldValueMap' => [
                'BasicIDV' => $mmv_data['idv'],
                'NIC_LimitZ' => '0',
                'AgreedValue' => '12617.27',
                'ManufacturerSellingPrice' => '735832',
                'NIC_Premium2' => '0',
                'NIC_Premium1' => '0',
            ],
            'coverTypeName' => 'Invoice Protect',
            'coverTypeId' => 700021420,
            'effectiveDate' => $policy_date['start'] . '000',
            'expirtyDate' => $policy_date['end'] . '000',
            'insuredId' => '',
            'policyCtId' => '',
            'policyCtAcceSOABOList' => [
                0 => [
                    'interestTypeCode' => 'Invoice Protect Sum Insured',
                    'interestDescription' => 'fdgdfh',
                    'interestSi' => 294333,
                    'interestType' => 700014040,
                    'policyCtId' => '',
                ]
            ],
        ];

        if ($is_new) {
            // $quote_request_data['invoice']['fieldValueMap']['NIC_TEA_VSL_EXTRA_SI'] = '0';
            // $quote_request_data['invoice']['fieldValueMap']['NIC_TEA_VSL_AGE'] = '0';

            // $quote_request_data['invoice']['fieldValueMap']['NIC_Deductible3'] = $mmv_data['idv'];
            // $quote_request_data['invoice']['fieldValueMap']['NIC_ExchangeRate'] = $mmv_data['idv2'];
            // $quote_request_data['invoice']['fieldValueMap']['NIC_Premium3'] = '968.16';
            // $quote_request_data['invoice']['fieldValueMap']['NIC_Supplier_Customer'] = $mmv_data['idv3'];
            // $quote_request_data['invoice']['siOfCoverType'] = $mmv_data['idv'];
        }
        return $invoice_request;
    }

    public static function create_rsa_request($policy_date, $mmv_data, $is_new)
    {

        $rsa_request = [
            'fieldValueMap' => [
                'NIC_LimitZ' => '0',
                'NIC_Premium2' => '0',
                'NIC_Premium1' => '0',
            ],
            'coverTypeName' => 'Road Side Assistance',
            'coverTypeId' => 700022514,
            'effectiveDate' => $policy_date['start'] . '000',
            'expirtyDate' => $policy_date['end'] . '000',
            'insuredId' => '',
            'policyCtId' => '',
            'siOfCoverType' => $mmv_data['idv'],
        ];

        // if($is_new)
        // {
        //     $quote_request_data['rsa']['fieldValueMap']['NIC_Deductible3'] = $mmv_data['idv'];
        //     $quote_request_data['rsa']['fieldValueMap']['NIC_ExchangeRate'] = $mmv_data['idv2'];
        //     $quote_request_data['rsa']['fieldValueMap']['NIC_Premium3'] = '968.16';
        //     $quote_request_data['rsa']['fieldValueMap']['NIC_Supplier_Customer'] = '0';
        //     $quote_request_data['rsa']['fieldValueMap']['NIC_TEA_VSL_AGE'] = '0';
        //     $quote_request_data['rsa']['fieldValueMap']['NIC_TEA_VSL_EXTRA_SI'] = '0';
        // }
        return $rsa_request;
    }

    public static function create_engine_protect_request($policy_date, $registration_date, $mmv_data, $is_new)
    {

        $engine_protect_request = [
            'fieldValueMap' => [
                'NIC_LimitZ' => '0',
                'AgreedValue' => '4',
                'NIC_Premium2' => '0',
                'NIC_Premium1' => '0',
                'NameOfTheEvent' => 'no changes',
                'NICDateOfManufacture' => $registration_date,
                'RalliesExtension' => '1',
            ],
            'coverTypeName' => 'Engine Protect',
            'coverTypeId' => 700021865,
            'effectiveDate' => $policy_date['start'] . '000',
            'expirtyDate' => $policy_date['end'] . '000',
            'insuredId' => '',
            'policyCtId' => '',
            'siOfCoverType' => $mmv_data['idv'],
        ];

        return $engine_protect_request;
    }

    public static function create_od_discount_request($is_liability)
    {
        $od_discount_request = [];

        if (!$is_liability) {
            $od_discount_request = [
                0 => [
                    'ctCode' => 'Own Damage Package_OD_P',
                    'ctId' => 700000500,
                    'discountRate' => 0.6,
                    'discountType' => 105,
                    'discountTypeCode' => 'Discount',
                ],
                1 => [
                    'ctCode' => 'Own Damage Package_OD_P',
                    'ctId' => 700000500,
                    'discountRate' => 0.1,
                    'discountType' => 91,
                    'discountTypeCode' => 'Loading',
                ],
            ];
        }

        return $od_discount_request;
    }
}
