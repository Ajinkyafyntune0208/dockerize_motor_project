<?php

use Carbon\Carbon;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Proposal\Services\NicSubmitProposal as NIC;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';
function getQuote($enquiryId, $requestData, $productData)
{

    try {
        // if (($requestData->ownership_changed ?? '') == 'Y') {
        //     return [
        //         'premium_amount' => 0,
        //         'status' => false,
        //         'message' => 'Quotes not allowed for ownership changed vehicle',
        //         'request' => [
        //             'message' => 'Quotes not allowed for ownership changed vehicle',
        //             'requestData' => $requestData
        //         ]
        //     ];
        // }
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        // dd($premium_type);
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

        $motor_manf_date = '01-' . $requestData->manufacture_year;

        if (!$is_new && !$is_liability && ($requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure')) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Break-In Quotes Not Allowed',
                'request' => [
                    'previous_policy_type' => $requestData->previous_policy_type,
                    'message' => 'Break-In Quotes Not Allowed',
                ]
            ];
        }

        if (empty($requestData->rto_code)) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'RTO not available',
                'request' => [
                    'rto_code' => $requestData->rto_code,
                    'message' => 'RTO not available',
                ]
            ];
        }

        $parent_code = get_parent_code($productData->product_sub_type_id);
        // dd($parent_code);

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
        } else {
            $mmv = get_mmv_details($productData, $requestData->version_id, 'nic');
        }
        $mmv_data = nic_mmv_check($mmv);


        if (!$mmv_data['status']) {
            return $mmv_data;
        }

        $mmv = $mmv_data['mmv_data'];
        //dd(Carbon::parse($motor_manf_date)->format('Y'));
        if (!$is_liability && !$is_new) {
            if (Carbon::parse($motor_manf_date)->format('Y') < $mmv->model_period_start || Carbon::parse($motor_manf_date)->format('Y') > $mmv->model_period_end) {
                return [
                    'premium_amount' => 0,
                    'status'         => false,
                    'message'        => 'Vehicle manufacturing year does not match with variant period (' . $mmv->model_period_start . ' - ' . $mmv->model_period_end . ').',
                    'request' => [
                        'manufacture_year' => $requestData->manufacture_year,
                        'veh_start_period' => $mmv->model_period_start,
                        'veh_end_period' => $mmv->model_period_end,
                    ]
                ];
            }
        }

        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();

        $date1          = new DateTime($requestData->vehicle_register_date);
        $date2          = new DateTime(
            $requestData->previous_policy_expiry_date == 'New'
                ? date('Y-m-d')
                : $requestData->previous_policy_expiry_date
        );
        $interval       = $date1->diff($date2);
        $age            = ($interval->y * 12) + $interval->m;
        $vehicle_age    = $interval->y;

        $selected_addons        = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons                 = ($selected_addons->addons == null ? [] : $selected_addons->addons);
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

        foreach ($discounts as $key => $data) {
            if ($data['name'] == 'anti-theft device' && !$is_liability) {
                $is_anti_theft = true;
            }

            if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) {
                $is_voluntary_access = true;
                $voluntary_excess_amt = $data['sumInsured'];
            }

            if ($data['name'] == 'TPPD Cover' && !$is_od) {
                $is_tppd = true;
                $tppd_amt = '9999';
            }
        }

        $is_cpa                     = ($is_individual && !$is_od) ? true : false;

        $addon['zero_dep'] = ((!$is_liability && $is_zero_dep && ($vehicle_age < 5)) ? true : false);
        $addon['rsa'] = ((!$is_liability) ? true : false);
        $addon['engine_protect'] = false; //((!$is_liability && ($vehicle_age < 5)) ? true : false);
        $addon['rti'] = ((!$is_liability && ($vehicle_age < 3)) ? true : false);

        $is_cpa = ($is_individual && !$is_od) ? true : false;

        $is_pa_unnamed      = (!$is_od && $PAforUnnamedPassenger) ? true : false;
        $is_pa_paid_driver  = (!$is_od && $PAforaddionaldPaidDriver) ? true : false;
        $pa_named           = false;

        $is_applicable['legal_liability']                   = (!$is_od && $llpaidDriver) ? true : false;
        $is_applicable['motor_anti_theft']                  = ((!$is_liability && $is_anti_theft) ? true : false);

        $is_applicable['motor_electric_accessories']        = ((!$is_liability && $Electricalaccess) ? true : false);
        $is_applicable['motor_non_electric_accessories']    = ((!$is_liability && $NonElectricalaccess) ? true : false);
        $is_applicable['motor_lpg_cng_kit']                 = ((!$is_liability && $externalCNGKIT) ? true : false);
        $is_applicable['automobile_association']            = ((!$is_liability && $autoMobileAssociation) ? true : false);

        if ($vehicle_age > 10) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Car Age greater than 10 years',
                'request' => [
                    'vehicle_age' => $vehicle_age,
                    'message' => 'Car Age greater than 10 years',
                ]
            ];
        }

        if ($is_zero_dep && $vehicle_age >= 5) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Zero Depriciation Cover Is Not Available For Vehicle Age More than 5 Years',
                'request' => [
                    'vehicle_age' => $vehicle_age,
                    'is_zero_dep' => 'true',
                    'message' => 'Zero Depriciation Cover Is Not Available For Vehicle Age More than 5 Years',
                ]
            ];
        }

        $vehicle_in_90_days = 0;

        $current_date = date('Y-m-d');

        if ($is_new) {
            $policy_start_date  = date('d-m-Y');
            $policy_end_date    = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 1 year'));
        } else {
            $datetime2      = new DateTime($current_date);
            $datetime1      = new DateTime($requestData->previous_policy_expiry_date);
            $intervals      = $datetime1->diff($datetime2);
            $difference     = $intervals->invert;

            $policy_start_date  = date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));

            if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)) {
                $policy_start_date = date('d-m-Y', strtotime('+1 day', time()));
            }

            $policy_end_date    = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 1 year'));

            $tp_start_date      = date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));
            $tp_end_date        = date('d-m-Y', strtotime($tp_start_date . '+1Y'));
        }
        $fuel = $mmv->nic_fuel_type_code;

        $mmvdata = (array)$mmv;
        $mmv->idv = $mmvdata['ex_showroom_price'];
        $idv2 = $mmvdata['idv_5_6_yr'];
        $idv3 = $mmvdata['idv_6_7_yr'];

        // if (!$is_new) {
        //     if ($age <= 6) {
        //         $mmv->idv =  $mmvdata['ex_showroom_price'];//$mmvdata['idv_6mon'];
        //     } else if ($age > 6 && $age <= 12) {
        //         $mmv->idv =  $mmvdata['ex_showroom_price'];//$mmvdata['idv_6monto1yr'];
        //     } else {
        //         for ($i = 1; $i < 10; $i++) {
        //             if ($age > ($i * 12) && $age <= (($i + 1) * 12)) {
        //                 $mmv->idv = $mmvdata['idv_' . $i . '_' . ($i + 1) . '_yr'];
        //                 break;
        //             }
        //         }
        //     }
        // }
        // unset($mmvdata);

        
        $mmv_data = [
            'manf_name'             => $mmv->make_name,
            'model_name'            => $mmv->model_name,
            'version_name'          => $mmv->variant_name,
            'seating_capacity'      => $mmv->seating_capacity,
            'carrying_capacity'     => $mmv->seating_capacity - 1,
            'cubic_capacity'        => $mmv->cubic_capacity,
            'fuel_type'             => $mmv->fuel_type,
            'gross_vehicle_weight'  => '',
            'vehicle_type'          => 'TRUCK',
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


        $customer_type  = $requestData->vehicle_owner_type == "I" ? "Individual" : "organization";

        $btype_code     = $requestData->business_type == "rollover" ? "2" : "1";
        $btype_name     = $requestData->business_type == "rollover" ? "Roll Over" : "New Business";

        if ($requestData->vehicle_registration_no != 'NEW') {
            if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != null) {
                $vehicle_register_no = explode('-', $requestData->vehicle_registration_no);
            } else {
                $vehicle_register_no = array_merge(explode('-', $requestData->rto_code), ['MGK', rand(1111, 9999)]);
            }
        } else {
            $vehicle_register_no = array_merge(explode('-', $requestData->rto_code), ['MGK', rand(1111, 9999)]);
        }

        $vehicle_register_no = $vehicle_register_no[0]
            . '-' . $vehicle_register_no[1]
            . '-' . $vehicle_register_no[2]
            . '-' . $vehicle_register_no[3];

        $rto_data = DB::table('nic_rto_master')->where('rto_number', strtr($requestData->rto_code, ['-' => '']))->first();

        // quick Quote Service

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

        if ($is_new) {
            $ncb['level']  = '0';
        }


        $customer = [
            'type' => ($is_individual ? 'Individual' : 'Corporate'),
            'first_name' => 'Sowmiyadevi',
            'last_name' => 'R',
            'email' => 'qwerty12@gmail.com',
            'mobile' => '9080967789',
            'marital_status' => '2',
            'gender' => '2',
            'dob' => 1051122600,
            'occupation' => '99611',
            'address' => '88, SAP Airforce City A, Gandhimaa Nagar',
            'pincode' => '112134',
            'state' => 'Maharashtra',
            'city' => 'Thane',
            'pan_no' => 'AAAAA1111A',
            'gstin' => '27AAAAA1111A1Z0',
            'salutation' => '1',
            'pincode_details' => [
                'status' => 'true',
                'state_name' => 'Maharashtra',
                'city_name' => 'Thane',
                'data' => [
                    'id' => '112134',
                    'city_id' => '641001',
                    'district_id' => '43003',
                    'state_id' => '43',
                    'pin_cd' => '100764',
                ],
            ],
            'quote' => $enquiryId,
            'section' => 'car',
            'method' => 'Customer_creation - Quote',
        ];
        // dd($customer);
        $customer_data = create_customer($customer, $enquiryId, $productData);
        // dd($customer_data);
        if ($customer_data['status'] == false) {
            return $customer_data;
        } else {
            $customer_id = $customer_data['customer_id'];
        }

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

        $quote_request_data['od_package'] = NIC::create_od_request($policy_date, $is_applicable, $registration_year, $is_new, $mmv_data, $is_liability);

        $cover_details = [
            'is_cpa' => [
                'is_applicable' => $is_cpa,
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




        $quote_request_data['pa'] = NIC::create_pa_request($policy_date, $cover_details, $is_new, $mmv_data);



        $quote_request_data['mmv'] = NIC::create_mmv_details_request($is_applicable, $addon, $mmv, $rto_data, $registration_year, $motor_manf_date, $is_new);

        $quote_request_data['legal_liability'] = NIC::create_legal_liability_tp_request($policy_date, $is_applicable, $is_new);


        // END ADDON REQUEST

        // $quote_request_data['invoice']          = NIC::create_invoice_request($policy_date, $mmv_data, $is_new);
        $quote_request_data['rsa']              = NIC::create_rsa_request($policy_date, $mmv_data, $is_new);
        // $quote_request_data['engine_protect']   = NIC::create_engine_protect_request($policy_date, $registration_date, $mmv_data, $is_new);

        // END ADDON REQUEST

        $quote_request_data['discount'] = NIC::create_od_discount_request($is_liability);

// dd($customer['mobile']);
        $quote_request_data['customer'] = [
            
            'addressLine' => $customer['address'],
            'addressType' => '2',
            'city' => $customer['pincode_details']['data']['city_id'],
            'country' => '91',
            'dateOfBirth' => 794361600000,
            'district' => $customer['pincode_details']['data']['district_id'],
            'email' => $customer['email'],
            'field02' => '1',
            'field10' => '05-03-1995',
            'firstName' => $customer['first_name'],
            'gender' => $customer['gender'],
            'lastName' => $customer['last_name'],
            'mobile' => $customer['mobile'],
            'occupation' => $customer['occupation'],
            'policyId' => '14606221',
            'postCode' => $customer['pincode'],
            'state' => $customer['pincode_details']['data']['state_id'],
            'title' => '3',
        ];
// dd($yn_claim);
        $quote_request_data['prev_policy'] = [
            0 => [
                'claimIncurredRatio' => '',//($yn_claim == 'N' ? 0 : 1),
                'policyFrom' => (int)$prev_policy['start'] . '000',
                'companyBranch' => 846108,
                'premiumPaid' => "23000",
                'listId' => '',
                'policyId' => '',
                'policyNo' => '776767567',
                'policyTo' => (int)$prev_policy['end'] . '000',
                'noClaims' => ($yn_claim == 'N' ? 0 : 1),
                'prevCompanyName' => 845016,
                'totClaimsIncurr' => ($yn_claim == 'N' ? "0" : "1"),
            ],
        ];
// dd($registration_date);
        $quote_request_data['vehicle'] = [
            'chasisNumber' => 'MAT445553DZJ68741',
            'engineNumber' => '2751D107JWYSJ4055',
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
// dd($mmv->idv);
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
                "NIC_TwoWheeler_Policy"=> "2",
                'NIC_Basis_of_Insurance_Policy_Level' => '1',
                'NIC_Financier_Address_PolicyLevel' => '',
                'NIC_Driver_Clause' => "Any person including insured: Provided that a person driving holds an effective driving license at the time of the accident and is not disqualified from holding or obtaining such a license. Provided also that the person holding an effective Learner's license may also drive the vehicle when not used for the transport of goods at the time of the accident and that such a person satisfies the requirements of Rule 3 of the Central Motor Vehicles Rules, 1989.",
                'NIC_Limitations' => "The Policy covers use only under a permit within the meaning of the Motor Vehicle Act, 1988 or such a carriage falling under Sub-Section 3 of Section 66 of the Motor Vehicle's Act 1988.The Policy does not cover:(1) Use for organized racing, pace-making, reliability trial or speed testing. (2) Use whilst towing any trailer's, except the trailer's insured with the Company, or the towing (other than for reward) of any one disabled mechanically propelled vehicles.(3) Use for carrying passengers in the vehicles except employees (other than the driver) not exceeding the number permitted in the registration document and coming under the purview of Workmen's Compensation Act 1923.",
            ],
            'poiPrintorNot' => 1,
            'policyId' => '',
            'actualAnnualTurnoverCarrying' => 0,
            'marineServiceTaxFlag' => '0',
            'ncdAmount' => '0',
            'estimatedAnnualTurnoverCarrying' => 0,
        ];

        // if(!$is_new)
        // {
        //     $genPolicyInfoSOABO['fieldValueMap']['NIC_Claims_Ratio_Loading']            = '40';
        //     $genPolicyInfoSOABO['fieldValueMap']['NIC_Long_Term_TP_Policy_Start_Date']  = ($is_od ? $tp_policy_date['start_date'].'000' : '');
        //     $genPolicyInfoSOABO['fieldValueMap']['NIC_Long_Term_TP_Policy_Expiry_Date'] = ($is_od ? $tp_policy_date['end_date'].'000' : '');
        //     $genPolicyInfoSOABO['fieldValueMap']['NIC_VoluntaryDeductibles']            = '598142';
        //     $genPolicyInfoSOABO['fieldValueMap']['NIC_MCDT_CIF_Policy_No']              = '98987856';
        //     $genPolicyInfoSOABO['fieldValueMap']['NIC_Financier_Policy']                = '598208';
        // }
        // dd($ncb['active']);
        $quote_array = [
            'userName' => 50010098,//config('constants.IcConstants.nic.USERNAME_NIC_CV_MOTOR'),
            'branchId' => 500100,//config('constants.IcConstants.nic.OFFICE_CODE_NIC_CV_MOTOR'),
            'customerId' => 9521278997,//$customer_id,
            'customerName' => $customer['first_name'].' '.$customer['last_name'],
            'customerType' => ($is_individual ? 'INDIVIDUAL' : 'CORPORATE'),
            'from' => 'AP',//config('constants.IcConstants.nic.FROM_NIC_CV_MOTOR'),
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
                'agreementCode' => '500100500100900000802901',//config('constants.IcConstants.nic.AGREEMENT_CODE_NIC_CV_MOTOR'),
                'effectDate' => $policy_date['start'] . '000',
                'expiryDate' => $policy_date['end'] . '000',
                'policyId' => '',
                'productCode' => $parent_code ?? '',
                'productId' => ($parent_code == 'GCV') ? 700000060 : 700000002,
                'productName' => ($parent_code == 'GCV') ? 'Goods Carrying Vehicle' : 'Motor - Passenger Carrying Vehicle',
                'quotationNumber' => '',
                'ncdLevel' => '',
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
                                            'NICMotorGEO03' => '1',
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
                        'planId' => '700000122',//$planId,
                        'policyId' => '',
                        'deletedPolicyCtSOABOList' => [],
                        'policyCtSOABOList' => [],
                        'vehicleInsuredSOABO' => $quote_request_data['vehicle'],
                    ],
                ],
                'policyId' => '',
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
                'previousInsuranceSOABOList' => $quote_request_data['prev_policy'], //($is_new ? [] : $quote_request_data['prev_policy']),
                'policyDiscSOABOList' => [],//$quote_request_data['discount'],
                'deletedPolicyDiscSOABOList' => [],//$quote_request_data['discount'],
            ],
        ];

        $quote_array['policySOABO']['insuredSOABOList'][0]['fieldValueMap'] = $quote_request_data['mmv'];

        $idv_quote_array = $quote_array;
// dd
        if (!$is_od) {
            array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['legal_liability']);
        }

        if (!$is_od && ($is_cpa || $pa_named || $is_pa_paid_driver || $is_pa_unnamed)) {
            array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['pa']);
        }

        // if (!$is_liability) {
        //     array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['od_package']);
        // }

        // if ($addon['rsa']) {
        //     array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['rsa']);
        // }

        // if ($addon['rti']) {
        //     array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['invoice']);
        // }

        if ($addon['engine_protect']) {
            array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['engine_protect']);
        }
        // dd($policy_start_date, $policy_end_date);
       
        // quick quote service input

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Save Quote',
            'transaction_type'  => 'quote',
            'productName'       => $productData->product_name,
            'content_type'      => 'text/plain',
            'WWW-Authenticate'  => config('constants.IcConstants.nic.AUTH_KEY_NIC_MOTOR'),
            'headers' => [
                'Content-Type'      => 'text/plain',
                'WWW-Authenticate'  => config('constants.IcConstants.nic.AUTH_KEY_NIC_MOTOR')
            ]
        ];
        // dd($quote_array);
        $get_response = getWsData(config('constants.IcConstants.nic.END_POINT_URL_NIC_MOTOR_PREMIUM'), $quote_array, 'nic', $additional_data);
        // dd($get_response);
        if ($get_response['response']) {
            $response = json_decode($get_response['response'], true);

            // echo "<pre>";print_r([$response, $vehicle_age, $addon, $is_applicable, $quote_array]);echo "</pre>";die();

            if ($response['responseCode'] != '999' || (isset($response['errorMessage']) && !empty($response['errorMessage']))) {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status'    => false,
                    'msg'       => $response['responseMessage'],
                    // 'message'   => $response['responseMessage'],
                    'is_applicable' => $is_applicable,
                    'idv'       => $mmv->idv,
                    '$quote_request'   => $response
                ];
            }

            $total_idv  = $mmv->idv;

            $min_idv = ceil($mmv->idv * 0.9);
            $max_idv = floor($mmv->idv * 1.2);

            // IDV change

            if (isset($requestData->is_idv_changed) && $requestData->is_idv_changed == 'Y') {
                if ($requestData->edit_idv >= $max_idv) {
                    $idv = (string)$max_idv;
                } elseif ($requestData->edit_idv <= $min_idv) {
                    $idv  = (string)$min_idv;
                } else {
                    $idv  = $requestData->edit_idv;
                }
            } else {
                $idv  = (string)$min_idv;
            }

            $idv_quote_array['policySOABO']['genPolicyInfoSOABO']['fieldValueMap']['NIC_CIF_Gross_Premium_of_CIF_Policy'] = $idv;

            $quote_request_data['rsa']['siOfCoverType'] = $idv;

            $quote_request_data['od_package']['policyCtAcceSOABOList'][0]['interestSi'] = $idv;

            $quote_request_data['od_package']['policyCtAcceSOABOList'][0]['fieldValueMap']['IManufacturerSellingPrice'] = $idv;
            $quote_request_data['od_package']['policyCtAcceSOABOList'][0]['fieldValueMap']['NIC_FestivalSumInsured_Benefit'] = $idv;

            $quote_request_data['invoice']['fieldValueMap']['BasicIDV'] = $idv;

            if (!$is_od) {
                array_push(
                    $idv_quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'],
                    $quote_request_data['legal_liability']
                );
            }
            if (!$is_od && ($is_cpa || $pa_named || $is_pa_paid_driver || $is_pa_unnamed)) {
                array_push(
                    $idv_quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'],
                    $quote_request_data['pa']
                );
            }
            // if (!$is_liability) {
            //     array_push(
            //         $idv_quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'],
            //         $quote_request_data['od_package']
            //     );
            // }
            // if ($addon['rsa']) {
            //     array_push(
            //         $idv_quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'],
            //         $quote_request_data['rsa']
            //     );
            // }
            if ($addon['rti']) {
                array_push($idv_quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['invoice']);
            }

            if ($addon['engine_protect']) {
                array_push($idv_quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['engine_protect']);
            }

            // return $quote_array;

            $additional_data = [
                'enquiryId'         => $enquiryId,
                'requestMethod'     => 'post',
                'section'           => $productData->product_sub_type_code,
                'method'            => 'Premium Calculation - IDV change',
                'transaction_type'  => 'quote',
                'productName'       => $productData->product_name,
                'content_type'      => 'text/plain',
                'WWW-Authenticate'  => config('constants.IcConstants.nic.AUTH_KEY_NIC_MOTOR'),
                'headers' => [
                    'Content-Type'      => 'text/plain',
                    'WWW-Authenticate'  => config('constants.IcConstants.nic.AUTH_KEY_NIC_MOTOR')
                ]
            ];
                 // dd($idv_quote_array);
            $get_response = getWsData(config('constants.IcConstants.nic.END_POINT_URL_NIC_MOTOR_PREMIUM'), $idv_quote_array, 'nic', $additional_data);
            // dd($get_response);
            if (isset($get_response['response']) && !empty($get_response['response'])) {
                $response = json_decode($get_response['response'], true);

                if ($response['responseCode'] != '999') {
                    return [
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status'    => false,
                        'msg'       => $response['responseMessage'],
                        'message'   => $response['responseMessage']
                    ];
                }
            } else {
                return  [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium_amount'    => '0',
                    'status'            => false,
                    'message'           => 'Car Insurer Not found',
                ];
            }

            $anti_theft_discount    = '0';
            $bonus_discount         = '0';
            $liability              = ($is_applicable['legal_liability'] ? 50 : 0);
            $electrical_amount      = '0';
            $non_electrical_amount  = '0';
            $lpg_cng_amount         = '0';
            $lpg_cng_tp_amount      = '0';
            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;

            $pa_unnamed             = ($is_pa_unnamed
                ? (
                    ($mmv_data['seating_capacity'] - 1)
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


            $premium['od']['total']         = isset($response['premiumBifurcation']['motorOD']) ? $response['premiumBifurcation']['motorOD'] : '0';
            $premium['tp']['total']         = isset($response['premiumBifurcation']['motorTP']) ? $response['premiumBifurcation']['motorTP'] : '0';
            $premium['premium']['tax']      = $tax;
            $premium['premium']['total']    = $response['premiumBifurcation']['premiumWithOutTax'];
            $premium['premium']['payble']   = $response['premiumBifurcation']['actualPremiumPayable'];

            $cover_codes = [
                '700000500' => 'od',
                '700006685' => 'tp',
                '700001440' => 'pa',

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
// dd($response);
            $covers['ods'] = $covers['od'];

            if (!$is_liability && $ncb['active']) {
                $covers['od'] = ($covers['od'] * 100 / (100 - $ncb['applicable']));
            } else {
                $covers['ncb'] = '0';
            }
// dd($liability);
            $covers['ncb'] = $covers['od'] * $ncb['applicable'] / 100;
            $covers['pa']  = ($covers['pa'] - $pa_unnamed);
            $covers['pa']  = ($covers['pa'] - $pa_paid_driver);

            $covers['tp']  = ($covers['tp'] - $liability);

            if ($is_liability) {
                $add_on_data = [
                    'in_built'   => [],
                    'additional' => [
                        'zero_depreciation'             => 0,
                        'road_side_assistance'          => 0,
                        'engine_protector'              => 0,
                        'return_to_invoice'             => 0,
                        'ncb_protection'                => 0,
                        'key_replace'                   => 0,
                        'consumables'                   => 0,
                        'tyre_secure'                   => 0,
                        'lopb'   => 0,
                    ],
                    'other'      => [],
                ];
            } else if ($addon['zero_dep']) {
                $add_on_data = [
                    'in_built'   => [],
                    'additional' => [
                        'zero_depreciation'             => $covers['zero_dep'],
                        'road_side_assistance'          => $covers['rsa'],
                        'engine_protector'              => $covers['engine_protect'],
                        'return_to_invoice'             => $covers['return_to_invoice'],
                        'ncb_protection'                => 0,
                        'key_replace'                   => 0,
                        'consumables'                   => 0,
                        'tyre_secure'                   => 0,
                        'lopb'   => 0,
                    ],
                    'other'      => [],
                ];
            } else {
                $add_on_data = [
                    'in_built'   => [],
                    'additional' => [
                        'zero_depreciation'             => 0,
                        'road_side_assistance'          => $covers['rsa'],
                        'engine_protector'              => $covers['engine_protect'],
                        'return_to_invoice'             => $covers['return_to_invoice'],
                        'ncb_protection'                => 0,
                        'key_replace'                   => 0,
                        'consumables'                   => 0,
                        'tyre_secure'                   => 0,
                        'lopb'   => 0,
                    ],
                    'other'      => [],
                ];
            }


            $in_built_premium = 0;
            foreach ($add_on_data['in_built'] as $key => $value) {
                $in_built_premium = $in_built_premium + $value;
            }

            $additional_premium = 0;
            // return $add_on_data['additional'];
            foreach ($add_on_data['additional'] as $key => $value) {
                $additional_premium = $additional_premium + $value;
            }

            $other_premium = 0;
            foreach ($add_on_data['other'] as $key => $value) {
                $other_premium = $other_premium + $value;
            }

            $add_on_data['in_built_premium'] = $in_built_premium;
            $add_on_data['additional_premium'] = $additional_premium;
            $add_on_data['other_premium'] = $other_premium;

            $applicable_addons = [
                'zeroDepreciation',
                'roadSideAssistance',
                'engineProtector',
                'returnToInvoice',
            ];

            if (!$addon['zero_dep']) {
                array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
            }

            if (!$addon['rsa']) {
                array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
            }

            if (!$addon['engine_protect']) {
                array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
            }

            if (!$addon['rti']) {
                array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
            }

            // return $quote_output;

            $total_od_premium           = $covers['od'];

            $total_tp_premium           = $covers['tp'] + $liability + $pa_paid_driver + $pa_unnamed;

            $total_discount_premium     = $covers['ncb'];

            $total_base_premium         = $total_od_premium +  $total_tp_premium - $total_discount_premium;
            //  dd($covers);
            $data_response = [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => true,
                'msg' => 'Found',
                'Data' => [
                    'idv' => $premium_type == 'third_party' ? 0 : $idv,
                    'min_idv' => $premium_type == 'third_party' ? 0 : ($min_idv),
                    'max_idv' => $premium_type == 'third_party' ? 0 : ($max_idv),
                    'qdata' => NULL,
                    'pp_enddate' => ($is_new ? '' : $requestData->previous_policy_expiry_date),
                    'addonCover' => NULL,
                    'addon_cover_data_get' => '',
                    'rto_decline' => NULL,
                    'rto_decline_number' => NULL,
                    'mmv_decline' => NULL,
                    'mmv_decline_name' => NULL,
                    'policy_type' => (($is_package) ? 'Comprehensive' : (($is_liability) ? 'Third Party' : 'Own Damage')),
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => "", //$premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['calculated'],
                    'vehicle_registration_no' => $requestData->rto_code,
                    'voluntary_excess' => '0',
                    'version_id' => $requestData->version_id,
                    'selected_addon' => [],
                    'showroom_price' => $premium_type == 'third_party' ? 0 : $idv,
                    'fuel_type' => $requestData->fuel_type,
                    'vehicle_idv' => $premium_type == 'third_party' ? 0 : $idv,
                    'ncb_discount' => ($is_liability ? '0' : $requestData->applicable_ncb),
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                    'product_name' => $productData->product_name,
                    'mmv_detail' => $mmv_data,
                    'master_policy_id' => [
                        'policy_id' => $productData->policy_id,
                        'policy_no' => $productData->policy_no,
                        'policy_start_date' => $policy_start_date,
                        'policy_end_date' => $policy_end_date,
                        'sum_insured' => $productData->sum_insured,
                        'corp_client_id' => $productData->corp_client_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'insurance_company_id' => $productData->company_id,
                        'status' => $productData->status,
                        'corp_name' => '',
                        'company_name' => $productData->company_name,
                        'logo' => env('APP_URL') . config('constants.motorConstant.logos') . $productData->logo,
                        'product_sub_type_name' => $productData->product_sub_type_name,
                        'flat_discount' => $productData->default_discount,
                        'predefine_series' => "",
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online
                    ],
                    'motor_manf_date' => $motor_manf_date,
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                    'vehicleDiscountValues' => [
                        'master_policy_id' => $productData->policy_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'segment_id' => '0',
                        'rto_cluster_id' => '0',
                        'car_age' => $vehicle_age,
                        'aai_discount' => 0,
                        'ic_vehicle_discount' => '',
                    ],
                    'basic_premium' => $covers['od'],
                    'deduction_of_ncb' => $covers['ncb'],
                    'tppd_premium_amount' => $covers['tp'],
                    'motor_electric_accessories_value' => '0', //need included
                    'motor_non_electric_accessories_value' => '0', //need included
                    'motor_lpg_cng_kit_value' => '0', //need included
                    'cover_unnamed_passenger_value' => $pa_unnamed,
                    'seating_capacity' => $mmv->seating_capacity,
                    'default_paid_driver' => '0',
                    'motor_additional_paid_driver' => $pa_paid_driver,
                    'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                    'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                    'compulsory_pa_own_driver' => $covers['pa'],
                    'total_accessories_amount(net_od_premium)' => "",
                    'total_own_damage' => $total_od_premium,
                    'cng_lpg_tp' => $lpg_cng_tp_amount,
                    'total_liability_premium' => $total_tp_premium,
                    'net_premium' => $response['premiumBifurcation']['actualPremiumPayable'],
                    'service_tax_amount' => "",
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'quotation_no' => '',
                    'premium_amount' => '',
                    'antitheft_discount' => '0', //need included
                    'final_od_premium' => $total_od_premium ?? 0,
                    'final_tp_premium' => $total_tp_premium ?? 0,
                    'final_total_discount' => $total_discount_premium,
                    'final_net_premium' => $total_base_premium ?? 0,
                    'final_gst_amount' => $response['premiumBifurcation']['premiumWithOutTax'] ?? 0,
                    'final_payable_amount' => $final_total_premium ?? 0,
                    'service_data_responseerr_msg' => '',
                    'user_id' => $requestData->user_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'user_product_journey_id' => $requestData->user_product_journey_id,
                    'business_type' => ($is_new ? 'New Business' : ($is_breakin ? 'Break-in' : 'Roll over')),
                    'service_err_code' => NULL,
                    'service_err_msg' => NULL,
                    'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                    'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
                    'ic_of' => $productData->company_id,
                    'vehicle_in_90_days' => $vehicle_in_90_days,
                    'get_policy_expiry_date' => NULL,
                    'get_changed_discount_quoteid' => 0,
                    'vehicle_discount_detail' => [
                        'discount_id' => NULL,
                        'discount_rate' => NULL
                    ],
                    'is_premium_online' => $productData->is_premium_online,
                    'is_proposal_online' => $productData->is_proposal_online,
                    'is_payment_online' => $productData->is_payment_online,
                    'policy_id' => $productData->policy_id,
                    'insurane_company_id' => $productData->company_id,
                    "max_addons_selection" => NULL,
                    'add_ons_data' =>    $add_on_data,
                    'applicable_addons' => $applicable_addons,
                ],
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
                ]
            ];

            return camelCase($data_response);
            // dd(camelCase($data_response))
        } else {
            return  [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium_amount'    => '0',
                'status'            => false,
                'message'           => 'Car Insurer Not found',
            ];
        }
    } catch (Exception $e) {
        return  [
            'premium_amount'    => '0',
            'status'            => false,
            'message'           => $e->getMessage(),
            'line'              => $e->getLine(),
            'file'              => $e->getFile(),
            'request' => [
                'requestData' => $requestData,
                'productData' => $productData
            ]
        ];
    }
}


function nic_mmv_check($mmv)
{
    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return    [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv,
            ]
        ];
    }
    $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($mmv->fyntune_version['version_id']) || $mmv->fyntune_version['version_id'] == '') {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle Not Mapped',
            ]
        ]);
    } elseif ($mmv->fyntune_version['version_id'] == 'DNE') {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]
        ]);
    } else {
        return ['status' => true, 'mmv_data' => $mmv];
    }
}


function create_customer($customer, $enquiryId, $productData)
{

    $is_individual = ($customer['type'] == 'Individual') ? true : false;

    $name = explode(' ', $customer['first_name']);
    $name[1] = (isset($name[1]) ? $name[1] : '');
        // dd($customer['address']);
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

        "pinCd"                 =>  $customer['pincode_details']['data']['id'],
        "Pin_Sl_No"             =>  $customer['pincode_details']['data']['id'],
        "stateName"             =>  $customer['pincode_details']['data']['state_id'],
        "cityName"              =>  $customer['pincode_details']['data']['city_id'],
        "districtName"          =>  $customer['pincode_details']['data']['district_id'],

        "occupation"            =>  $customer['occupation'],
        "maritalStatus"         =>  $customer['marital_status'],

        "corporateName"         => (!$is_individual ? $customer['last_name'] : ''),
        "industryType"          => ($is_individual ? '' : '19211901'),
        "organizationType"      => ($is_individual ? '' : '11'),

        'firstName'             => ($is_individual ? $customer['first_name'] : $name[0]),
        'lastName'              => ($is_individual ? $customer['last_name'] : $name[1]),

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
        "accountNo"                   =>  "",
        "bankAccountName"             =>  "",
        "bankAccountType"             =>  "",
        "neftCode"                    =>  "",
        "bankCity"                    =>  "",
        "bankNm"                      =>  "",
        "bankCode"                    =>  "",
        "bankBranchName"              =>  "",
        "micrCode"                    =>  "",
        "rtgsCode"                    =>  "",
        "paymentOption"               =>  "",
        "branchAddress"               =>  "",
        "customerCode"                =>  "",
        "searchPinCode"               =>  "",

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
        'method'            => 'Customer Creation',
        'transaction_type'  => 'quote',
        'productName'       => $productData->product_name,
        'content_type'      => 'text/plain',
        'WWW-Authenticate'  => config('constants.IcConstants.nic.AUTH_KEY_NIC_MOTOR'),
        'headers' => [
            'Content-Type'      => 'text/plain',
            'WWW-Authenticate'  => config('constants.IcConstants.nic.AUTH_KEY_NIC_MOTOR')
        ]
    ];

    $createCustomerResponse = getWsData(config('constants.IcConstants.nic.END_POINT_URL_NIC_CUSTOMER_CREATION'), $createCustomerRequest, 'nic', $additional_data);
    // dd($createCustomerResponse);
    if ($createCustomerResponse['response']) {
        $str["from"]   = strpos($createCustomerResponse['response'], "[");
        $str["to"]     = strpos($createCustomerResponse['response'], "]");
        $str["rem"]    = $str["to"] - $str["from"];

        $str["customer_id"] = substr($createCustomerResponse['response'], $str["from"] + 1, $str["rem"] - 1);

        if ($str["customer_id"]) {
            return [
                'webservice_id' => $createCustomerResponse['webservice_id'],
                'table' => $createCustomerResponse['table'],
                "status"        => 'true',
                "customer_id"   => $str["customer_id"],
            ];
        } else {
            return [
                'webservice_id' => $createCustomerResponse['webservice_id'],
                'table' => $createCustomerResponse['table'],
                "status"    => 'false',
                "message"   => $createCustomerResponse,
                "customer_id"   => '',
            ];
        }
    } else {
        return [
            'webservice_id' => $createCustomerResponse['webservice_id'],
            'table' => $createCustomerResponse['table'],
            "status"    => 'false',
            "message"   => 'insurer not reachable',
            "customer_id"   => '',
        ];
    }
}

function getNicDateFormat($date){
    return (int)strtotime($date) * 1000;
}
