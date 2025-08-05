<?php

use Carbon\Carbon;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;
use App\Http\Controllers\Proposal\Services\Bike\NicSubmitProposal as NIC;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
function getQuote($enquiryId, $requestData, $productData)
{
    try{
        // if(($requestData->ownership_changed ?? '' ) == 'Y')
        // {
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

        if(!$is_new && !$is_liability && ( $requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure'))
        {
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
                    'rto_code' =>$requestData->rto_code,
                    'message' => 'RTO not available',
                ]
            ];
        }

        // $mmv = get_mmv_details($productData, $requestData->version_id, 'nic');

        // $mmv_data = nic_mmv_check($mmv);

        // if(!$mmv_data['status']){
        //     return $mmv_data;
        // }

        // $mmv = $mmv_data['mmv_data'];

        // if(!$is_liability)
        // {
        //     if(Carbon::parse($motor_manf_date)->format('Y') < $mmv->model_period_start || Carbon::parse($motor_manf_date)->format('Y') > $mmv->model_period_end)
        //     {
        //         return [
        //             'premium_amount' => 0,
        //             'status'         => false,
        //             'message'        => 'Vehicle manufacturing year does not match with variant period ('.$mmv->model_period_start.' - '.$mmv->model_period_end.').',
        //             'request' => [
        //                 'manufacture_year' => $requestData->manufacture_year,
        //                 'veh_start_period' => $mmv->model_period_start,
        //                 'veh_end_period' => $mmv->model_period_end,
        //             ]
        //         ];
        //     }
        // }

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
        $acc_applicables = [
            "is_electrical"=> $Electricalaccess,
            "electric_si"=> $ElectricalaccessSI,
            "is_non_electrical"=> $NonElectricalaccess,
            "non_electric_si" => $NonElectricalaccessSI
         ];

        $is_cpa                     = ($is_individual && !$is_od) ? true : false;  

        $addon['zero_dep'] = ((!$is_liability && $is_zero_dep && ($vehicle_age < 5)) ? true : false);
        $addon['rsa'] = ((!$is_liability) ? true : false);
        $addon['engine_protect'] = ((!$is_liability && ($vehicle_age < 5)) ? true : false);
        $addon['rti'] = ((!$is_liability && ($vehicle_age < 3)) ? true : false);

        $is_cpa = ($is_individual && !$is_od) ? true : false;

        $is_pa_unnamed      = (!$is_od && $PAforUnnamedPassenger) ? true : false;
        $is_pa_paid_driver  = (!$is_od && $PAforaddionaldPaidDriver) ? true : false;
        $pa_named           = false;

        $is_applicable['legal_liability']                   = (!$is_od && $llpaidDriver) ? true : false;      // dump($is_applicable['legal_liability']);
        $is_applicable['motor_anti_theft']                  = ((!$is_liability && $is_anti_theft) ? true : false);

        $is_applicable['motor_electric_accessories']        = ((!$is_liability && $Electricalaccess) ? true : false);
        $is_applicable['motor_non_electric_accessories']    = ((!$is_liability && $NonElectricalaccess) ? true : false);
        $is_applicable['motor_lpg_cng_kit']                 = ((!$is_liability && $externalCNGKIT) ? true : false);
        $is_applicable['automobile_association']            = ((!$is_liability && $autoMobileAssociation) ? true : false);
        // end additional covers

        if ($vehicle_age > 10) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Bike Age greater than 10 years',
                'request' => [
                    'vehicle_age' =>$vehicle_age,
                    'message' => 'Bike Age greater than 10 years',
                ]
            ];
        }

        if ($is_zero_dep && $vehicle_age >= 5) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Zero Depriciation Cover Is Not Available For Vehicle Age More than 5 Years',
                'request' => [
                    'vehicle_age' =>$vehicle_age,
                    'is_zero_dep' => 'true',
                    'message' => 'Zero Depriciation Cover Is Not Available For Vehicle Age More than 5 Years',
                ]
            ];
        }

        $vehicle_in_90_days = 0;

        $current_date = date('Y-m-d');

        if($is_new)
        {
            $policy_start_date  = date('d-m-Y');
            $policy_end_date    = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 5 year'));
        }
        else
        {
            $datetime2      = new DateTime($current_date);
            // $datetime1      = new DateTime($requestData->previous_policy_expiry_date);
             $datetime1 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('d-m-Y') : $requestData->previous_policy_expiry_date);
            $intervals      = $datetime1->diff($datetime2);
            $difference     = $intervals->invert;

            $policy_start_date  = date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));

            if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)) {
                $policy_start_date = date('d-m-Y', strtotime('+1 day', time()));
            }

            $policy_end_date    = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 3 year'));

            $tp_start_date      = date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));
            $tp_end_date        = date('d-m-Y', strtotime($tp_start_date.'+5Y'));
        }

        $mmv_data = [
            'idv'               => '50000',
            'idv2'              => '45000',
            'idv3'              => '40000',
            'nic_make_code'     => '894006',
            'nic_model_code'    => '894006002',
            'nic_variant_code'  => '137',
            'seating_capacity'  => '2',
            'ic_version_code' => 'IBB116009001'
        ];
        $idv2              = '45000';
        $idv3              = '40000';

        // $fuel = $mmv->nic_fuel_type_code;

        // $mmvdata = (array)$mmv;

        // $mmv_data['idv'] = $mmvdata['ex_showroom_price'];
        // $idv2 = $mmvdata['idv_1_2_yr'];
        // $idv3 = $mmvdata['idv_2_3_yr'];

        // if($age <= 6)
        // {
        //     $mmv_data['idv'] = $mmvdata['idv_6mon'];
        // }
        // else if($age > 6 && $age <= 12)
        // {
        //     $mmv_data['idv'] = $mmvdata['idv_6monto1yr'];
        // }
        // else
        // {
        //     for ($i=1; $i < 10; $i++) {
        //         if($age > ($i*12) && $age <= (($i+1)*12)){
        //             $mmv_data['idv'] = $mmvdata['idv_'.$i.'_'.($i+1).'_yr'];
        //             break;
        //         }
        //     }
        // }
        // unset($mmvdata);


        // $mmv_data = [
        //     'manf_name'             => $mmv->make_name,
        //     'model_name'            => $mmv->model_name,
        //     'version_name'          => $mmv->variant_name,
        //     'seating_capacity'      => $mmv->seating_capacity,
        //     'carrying_capacity'     => $mmv->seating_capacity - 1,
        //     'cubic_capacity'        => $mmv->cubic_capacity,
        //     'fuel_type'             => $mmv->fuel_type,
        //     'gross_vehicle_weight'  => '',
        //     'vehicle_type'          => 'BIKE',
        //     'version_id'            => $mmv->ic_version_code,
        //     'idv'                   => $mmv_data['idv'],
        //     'idv2'                  => $idv2,
        //     'idv3'                  => $idv3,
        //     'time_period'           => [
        //         'manufacture_year'      => $requestData->manufacture_year,
        //         'veh_start_period'      => $mmv->model_period_start,
        //         'veh_end_period'        => $mmv->model_period_end,
        //     ]
        // ];

        $customer_type  = $requestData->vehicle_owner_type == "I" ? "Individual" : "organization";

        $btype_code     = $requestData->business_type == "rollover" ? "2" : "1";
        $btype_name     = $requestData->business_type == "rollover" ? "Roll Over" : "New Business";

        if($requestData->vehicle_registration_no != 'NEW'){
            if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != null) {
                $vehicle_register_no = explode('-', $requestData->vehicle_registration_no);
            } else {
                $vehicle_register_no = array_merge(explode('-', $requestData->rto_code), ['MGK', rand(1111, 9999)]);
            }
        }
        else
        {
            $vehicle_register_no = array_merge(explode('-', $requestData->rto_code), ['MGK', rand(1111, 9999)]);
        }

        $vehicle_register_no = $vehicle_register_no[0]
            .'-'. $vehicle_register_no[1]
            .'-'. $vehicle_register_no[2]
            .'-'. $vehicle_register_no[3];

        $rto_data = DB::table('nic_rto_master')->where('rto_number', strtr($requestData->rto_code, ['-' => '']))->first();

        // quick Quote Service

        $proposal_date          = date('d/m/Y');

        // $registration_year      = Carbon::parse($motor_manf_date)->format('Y');
        $registration_year      = strtotime($motor_manf_date).'000';
        $registration_date      = strtotime($requestData->vehicle_register_date).'000';

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

        $ncb_levels = ['0'=>'0','20'=>'1','25'=>'2','35'=>'3','45'=>'4','50'=>'5'];

        if ($requestData->is_claim == 'N')
        {
            $yn_claim           = 'N';
            $ncb['active']      = true;
            $ncb['current']     = $requestData->previous_ncb;
            $ncb['applicable']  = $requestData->applicable_ncb;
            $ncb['level']       = $ncb_levels[$ncb['applicable']];
        }

        if($is_new){
            $ncb['level']  = '0';
        }


        $customer = [
            'type' => ($is_individual ? 'Individual' : 'Corporate'),
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'example@sample.domain',
            'mobile' => '8989989898',
            'marital_status' => '2',
            'gender' => '1',
            'dob' => 1051122600,
            'occupation' => '17003',
            'address' => 'sfsdf,324re,sdrwr',
            'pincode' => '400705',
            'state' => 'Maharashtra',
            'city' => 'Thane',
            'pan_no' => 'AAAAA1111A',
            'gstin' => '27AAAAA1111A1Z0',
            'salutation' => '1',
            'pincode_details' => [
                'status' => true,
                'state_name' => 'Maharashtra',
                'city_name' => 'Thane',
                'data' => [
                    'id' => '48075',
                    'city_id' => '924032',
                    'district_id' => '24032',
                    'state_id' => '24',
                    'pin_cd' => '400705',
                ],
            ],
            'quote' => $enquiryId,
            'section' => 'bike',
            'method' => 'Customer_creation - Quote',
        ];

        $customer_data = create_customer($customer, $enquiryId, $productData);

        if($customer_data['status'] == false)
        {
            return $customer_data;
        }
        else
        {
            $customer_id = $customer_data['customer_id'];
        }
        $customer_id = "9521268898";

        $planId = (
            $is_new
            ? (
                $is_package
                ? 700015914
                : 700015908)
            : (
                $is_package
                ? 700000460
                : (
                    $is_liability
                    ? 700000461
                    : 700015981)
            )
        );

        $quote_request_data['od_package'] = NIC::create_od_request($policy_date, $is_applicable, $registration_year, $is_new, $mmv_data, $is_liability  ,$acc_applicables );

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
        $quote_request_data['mmv'] = NIC::create_mmv_details_request($is_applicable, $addon, $mmv_data, $rto_data, $registration_year, $motor_manf_date);
        $quote_request_data['legal_liability'] = NIC::create_legal_liability_tp_request($policy_date, $is_applicable, $is_new);

        // END ADDON REQUEST

        $quote_request_data['invoice']          = NIC::create_invoice_request($policy_date, $mmv_data, $is_new);
        $quote_request_data['rsa']              = NIC::create_rsa_request($policy_date, $mmv_data, $is_new);
        $quote_request_data['engine_protect']   = NIC::create_engine_protect_request($policy_date, $registration_date, $mmv_data, $is_new);

        // END ADDON REQUEST

        $quote_request_data['customer'] = [
            'policyId' => '',
            'firstName' => 'Brasha',
            'middleName' => '',
            'lastName' => 'Mohanty',
            'gender' => '2',
            'title' => '4',
            'dateOfBirth' => 1062613800000,
            'maritalStatus' => '2',
            'language' => '1010',
            'occupation' => '15112991',
            'mobile' => '9051427645',
            'email' => 'barsha@gmail.com',
            'addressLine' => 'Trichi',
            'city' => '625001',
            'district' => '43011',
            'state' => '43',
            'country' => '91',
            'postCode' => '106558',
            'addressType' => '2',
            'field01' => 'SATPD7652R',
            'field02' => '1',
            'field04' => '33AAACN9967E1ZA',
            'field05' => 'N',
        ];

        $quote_request_data['prev_policy'] = [
            0 => [
                'listId' => '',
                'policyId' => '',
                'policyNo' => '',
                'policyFrom' => $prev_policy['start'].'000',
                'policyTo' => $prev_policy['end'].'000',
                'prevCompanyName' => '598208',
                'companyBranch' => '598142',
                'premiumPaid' => 3400,
                'noClaims' => ($yn_claim == 'N' ? 0 : 1),
                'totClaimsIncurr' => ($yn_claim == 'N' ? 0 : 1),
                'claimIncurredRatio' => ($yn_claim == 'N' ? '0' : '1'),
            ],
        ];

        $quote_request_data['discount'] = [];
        
        if(!$is_liability){
            $quote_request_data['discount'] = [
                0 => [
                    'ctId' => 700002150,
                    'discountRate' => 0,
                    'discLoadingId' => '',
                    'discountType' => 105,
                    'discountTypeCode' => 'Discount',
                ],
                1 => [
                    'ctId' => 700002150,
                    'discountRate' => 0,
                    'discLoadingId' => '',
                    'discountType' => 91,
                    'discountTypeCode' => 'Loading',
                ],
            ];
        }

        $quote_request_data['vehicle'] = [
            'chasisNumber' => 'EASE123ASD345AS12',
            'engineNumber' => 'ABCDEFGR122423235',
            'ncdAmount' => 0,
            'ncdEntitlement' => 0,
            'rejectFlag' => false,
            'roadTax' => 0,
            'numberofSeats' => $mmv_data['seating_capacity'],
            'vehicleAge' => floor($age/12),
            'vehicleRegisterDate' => $registration_date,
            'vehicleRegisterNo' => $vehicle_register_no,
            'weight' => 0,
            'claimDescription' => '',
            'capacity'          => 97
        ];

        $genPolicyInfoSOABO = [
            'fieldValueMap' => [
                'NIC_Amount' => '0',
                'NIC_PeriodA' => '0',
                'NIC_GMCT_OP_Lmt_Per_Clm' => '0',
                'NIC_CIF_Gross_Premium_of_CIF_Policy' => $mmv_data['idv'],
                'AnyLossOrDamage' => '2',
                'NIC_Driver_Clause' => '',
                'NIC_Limitations' => '',
                'HasErectionStart' => ($ncb['active'] ? '1' : '2'),
                'NIC_HHI_Exp_Time' => '0',
                'NIC_MISS_P_01' => '0',
                'NIC_TotalExcess' => '0',
                'NIC_ClaimExp_Percent' => '0',
                'NIC_Volume_Discount' => '0',
                'NIC_GMCT_TPA_Option' => '2',
                'NIC_NumberB' => $idv3,
                'NIC_ST_Exempted_EOU_OR_SEZ' => '2',
                'NIC_CIF_Sum_Insured' => '47500',
                'NIC_Basis_of_Insurance_Policy_Level' => '3',
            ],
            'actualAnnualTurnoverCarrying' => 0,
            'marineServiceTaxFlag' => '0',
            'durationMonth' => 12,
            'estimatedAnnualTurnoverCarrying' => 0,
            'ncdAmount' => 0,
            'poiPrintorNot' => 1,
            'policyId' => '',
        ];

        if(!$is_new)
        {
            $genPolicyInfoSOABO['fieldValueMap']['NIC_Claims_Ratio_Loading']            = '5';
            // $genPolicyInfoSOABO['fieldValueMap']['NIC_Long_Term_TP_Policy_Start_Date']  = ($is_od ? $tp_policy_date['start_date'].'000' : '');
            // $genPolicyInfoSOABO['fieldValueMap']['NIC_Long_Term_TP_Policy_Expiry_Date'] = ($is_od ? $tp_policy_date['end_date'].'000' : '');
            // $genPolicyInfoSOABO['fieldValueMap']['NIC_VoluntaryDeductibles']            = '598142';
            // $genPolicyInfoSOABO['fieldValueMap']['NIC_MCDT_CIF_Policy_No']              = '98987856';
            // $genPolicyInfoSOABO['fieldValueMap']['NIC_Financier_Policy']                = '598208';
        }

        $quote_array = [
            'userName' => config('constants.IcConstants.nic.USERNAME_NIC_MOTOR'),
            'branchId' => config('constants.IcConstants.nic.OFFICE_CODE_NIC_MOTOR'),
            'customerId' => $customer_id,
            'customerName' => 'AGENT PORTAL TEAM',
            'customerType' => ($is_individual ? 'INDIVIDUAL' : 'CORPORATE'),
            'from' => config('constants.IcConstants.nic.FROM_NIC_MOTOR'),
            'textAttribute1' => '2',
            'policySOABO' => [
                'dynamicObjectList' => [
                    0 => [
                        'bizTableName' => 'NIC_FINANCIER_POLICY',
                        'dynamicAttributeVOList' => [
                            0 => [
                                'valueMap' => [
                                    'FinancierAddress' => '',
                                    'POLICYID' => '',
                                    'FinancierName' => '',
                                    'PartyID' => '',
                                    'Party_ID' => '',
                                    'AgreementType' => '',
                                    'DYN_DATA_ID' => '',
                                ],
                            ],
                        ],
                    ],
                ],
                'agreementCode' => config('constants.IcConstants.nic.AGREEMENT_CODE_NIC_MOTOR'),
                'effectDate' => $policy_date['start'].'000',
                'expiryDate' => $policy_date['end'].'000',
                'policyId' => '',
                'productCode' => 'MCY',
                'productId' => 700001000,
                'productName' => 'Motor - Two Wheelers',
                'ncdLevel' => $ncb['level'],
                'quotationNumber' => '',
                'proposalStatus' => '',
                'genPolicyInfoSOABO' => $genPolicyInfoSOABO,
                'insuredSOABOList' => [
                    0 => [
                        'fieldValueMap' => [],//$quote_request_data['mmv'],
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
                        'effectiveDate' => $policy_date['start'].'000',
                        'expiryDate' => $policy_date['end'].'000',
                        'insuredCategory' => 1,
                        'insuredId' => '',
                        'insuredName' => 'Virtual Insured',
                        'planId' => $planId,
                        'policyId' => '',
                        'policyCtSOABOList' => [
                        ],
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
                'previousInsuranceSOABOList' => ($is_new ? [] : $quote_request_data['prev_policy']),
                'policyDiscSOABOList' => $quote_request_data['discount'],
            ],
        ];

        $quote_array['policySOABO']['insuredSOABOList'][0]['fieldValueMap'] = $quote_request_data['mmv'];

        $idv_quote_array = $quote_array;

        if(!$is_od){
            array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['legal_liability']);
        }

        if(!$is_od && ($is_cpa || $pa_named || $is_pa_paid_driver || $is_pa_unnamed)){
            array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['pa']);
        }

        if(!$is_liability){
            array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['od_package']);
        }

        if($addon['rsa']) {
            array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['rsa']);
        }

        // if($addon['rti']) {
        //     array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['invoice']);
        // }

        if($addon['engine_protect'])
        {
            array_push($quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['engine_protect']);
        }

        // quick quote service input

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Premium Calculation',
            'transaction_type'  => 'quote',
            'productName'       => $productData->product_name,
           'headers'            =>[
            'content-type'      => 'text/plain',
              'WWW-Authenticate'  => config('constants.IcConstants.nic.AUTH_KEY_NIC_MOTOR')
            ]
        ];

        $get_response = getWsData(config('constants.IcConstants.nic.END_POINT_URL_NIC_MOTOR_PREMIUM'), $quote_array, 'nic', $additional_data);

        $response=$get_response['response'];
        if ($response) {
            $response = json_decode($response, true);

            if($response['responseCode'] != '999'){
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status'    => false,
                    'msg'       => $response['responseMessage'],
                    // 'message'   => $response['responseMessage'],
                    'is_applicable' => $is_applicable,
                    'idv'       => $mmv_data['idv'],
                    // '$quote_request'   => json_encode($quote_array)
                ];
            }

            $total_idv  = $mmv_data['idv'];

            $min_idv = ceil($mmv_data['idv'] * 0.9);
            $max_idv = floor($mmv_data['idv'] * 1.2);

            // IDV change

            if (isset($requestData->is_idv_changed) && $requestData->is_idv_changed == 'Y')
            {
                if ($requestData->edit_idv >= $max_idv)
                {
                    $idv = (string)$max_idv;
                }
                elseif ($requestData->edit_idv <= $min_idv)
                {
                    $idv  = (string)$min_idv;
                }
                else
                {
                    $idv  = $requestData->edit_idv;
                }
            }else{
                $idv  = (string)$min_idv;
            }

            $idv_quote_array['policySOABO']['genPolicyInfoSOABO']['fieldValueMap']['NIC_CIF_Gross_Premium_of_CIF_Policy'] = $idv;

            $quote_request_data['rsa']['siOfCoverType'] = $idv;

            $quote_request_data['od_package']['policyCtAcceSOABOList'][0]['interestSi'] = $idv;

            // $quote_request_data['od_package']['policyCtAcceSOABOList'][0]['fieldValueMap']['IManufacturerSellingPrice'] = $idv;

            $quote_request_data['invoice']['fieldValueMap']['BasicIDV'] = $idv;

            if(!$is_od){
                array_push(
                    $idv_quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'],
                    $quote_request_data['legal_liability']
                );
            }
            if(!$is_od && ($is_cpa || $pa_named || $is_pa_paid_driver || $is_pa_unnamed)){
                array_push(
                    $idv_quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'],
                    $quote_request_data['pa']
                );
            }
            if(!$is_liability){
                array_push(
                    $idv_quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'],
                    $quote_request_data['od_package']
                );
            }
            if($addon['rsa']) {
                array_push(
                    $idv_quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'],
                    $quote_request_data['rsa']
                );
            }
            // if($addon['rti']) {
            //     array_push($idv_quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['invoice']);
            // }

            if($addon['engine_protect'])
            {
                array_push($idv_quote_array['policySOABO']['insuredSOABOList'][0]['policyCtSOABOList'], $quote_request_data['engine_protect']);
            }

            // return $quote_array;

            $additional_data = [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'enquiryId'         => $enquiryId,
                'requestMethod'     => 'post',
                'section'           => $productData->product_sub_type_code,
                'method'            => 'Premium Re-Calculation',
                'transaction_type'  => 'quote',
                'productName'       => $productData->product_name,
                'headers'            =>[
                    'content-type'      => 'text/plain',
                  'WWW-Authenticate'  => config('constants.IcConstants.nic.AUTH_KEY_NIC_MOTOR')  
                ]
            ];

            $get_response = getWsData(config('constants.IcConstants.nic.END_POINT_URL_NIC_MOTOR_PREMIUM'), $idv_quote_array, 'nic', $additional_data);

            $response=$get_response['response'];
            if ($response)
            {
                $response = json_decode($response, true);

                if($response['responseCode'] != '999'){
                    return [
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status'    => false,
                        'msg'       => $response['responseMessage'],
                        'message'   => $response['responseMessage']
                    ];
                }
            }
            else
            {
                return  [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium_amount'    => '0',
                    'status'            => false,
                    'message'           => 'Bike Insurer Not found',
                ];
            }

            $anti_theft_discount    = 0;
            $bonus_discount         = 0;
            $liability              = ($is_applicable['legal_liability'] ? 50 : 0);
            $electrical_amount      = $response['policyCtAcceSOABOList']['Electrical Accessories'] ?? 0;
            $non_electrical_amount  = $response['policyCtAcceSOABOList']['Non Electrical Accessories'] ?? 0;
            $lpg_cng_amount         = 0;
            $lpg_cng_tp_amount      = 0;

            $pa_unnamed             = (
                $is_pa_unnamed
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
                : 0
            );

            $pa_paid_driver = (($is_pa_paid_driver) ? (($PAforaddionaldPaidDriverSI == '100000') ?'50' : '100') : '0');

            $tax = 0;

            if(isset($response['premiumBifurcation']['IGST'])) {
                $tax = $response['premiumBifurcation']['IGST'];
            }else{
                $tax = $response['premiumBifurcation']['CGST'] + $response['premiumBifurcation']['SGST-UTGST'];
            }

            $premium['od']['total']         = isset($response['premiumBifurcation']['motorOD']) ? $response['premiumBifurcation']['motorOD'] : '0';
            $premium['tp']['total']         = isset($response['premiumBifurcation']['motorTP']) ? $response['premiumBifurcation']['motorTP'] : '0';
            $premium['premium']['tax']      = $tax;
            $premium['premium']['total']    = $response['premiumBifurcation']['premiumWithOutTax'];
            $premium['premium']['payble']   = $response['premiumBifurcation']['actualPremiumPayable'];

            $cover_codes = [
                '700002150' => 'od',
                '700002152' => 'tp',
                '700002151' => 'pa',
                '700021420' => 'return_to_invoice',
                '700022514' => 'rsa',
                '700022408' => 'zero_dep',
                '700021863' => 'engine_protect',
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
                'legal_liability'   => $liability
            ];

            foreach ($cover_codes as $key => $value) {
                if(isset($response['quotationEditIds']['coverPremium_'.$key])){
                    $covers[$value] = (int)$response['quotationEditIds']['coverPremium_'.$key];
                }
            }

            $covers['ods'] = $covers['od'];

            if(!$is_liability && $ncb['active']){
                $covers['od'] = ($covers['od'] * 100 / (100 - $ncb['applicable']));
            }else{
                $covers['ncb'] = '0';
            }

            $covers['ncb'] = $covers['od'] * $ncb['applicable']/100;
            $covers['pa']  = ($covers['pa'] - $pa_unnamed);
            $covers['pa']  = ($covers['pa'] - $pa_paid_driver);

            $covers['tp']  = ($covers['tp'] - $liability);

            if($is_liability){
                $add_on_data = [
                    'in_built'   => [],
                    'additional' => [
                        'zero_depreciation'             => 0,
                        'road_side_assistance'          => 0,
                    ],
                    'other'      => [],
                ];
            }
            else if($addon['zero_dep'])
            {
                $add_on_data = [
                    'in_built'   => [],
                    'additional' => [
                        'zero_depreciation'             => $covers['zero_dep'],
                        // 'road_side_assistance'          => $covers['rsa'],
                        'engine_protector'              => $covers['engine_protect'],
                    ],
                    'other'      => [],
                ];
            }
            else
            {
                $add_on_data = [
                    'in_built'   => [],
                    'additional' => [
                        'zero_depreciation'             => 0,
                        'road_side_assistance'          => $covers['rsa'],
                        'engine_protector'              => $covers['engine_protect'],
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
                // 'roadSideAssistance',
                'engineProtector',
                // 'returnToInvoice',
            ];

            if (!$addon['zero_dep'])
            {
                array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
            }

            if (!$addon['rsa'])
            {
                array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
            }

            if (!$addon['engine_protect'])
            {
                array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
            }

            if (!$addon['rti'])
            {
                array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
            }

            // return $quote_output;

            $total_od_premium           = $covers['od'];

            $total_tp_premium           = $covers['tp'] + $liability + $pa_paid_driver + $pa_unnamed;

            $total_discount_premium     = $covers['ncb'];

            $total_base_premium         = $total_od_premium +  $total_tp_premium - $total_discount_premium;

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
                    'voluntary_excess' => 0,
                    'version_id' => $requestData->version_id,
                    'selected_addon' => [],
                    'showroom_price' => $premium_type == 'third_party' ? 0 : $idv,
                    'fuel_type' => $requestData->fuel_type,
                    'vehicle_idv' => $premium_type == 'third_party' ? 0 : $idv,
                    'ncb_discount' =>  ($is_liability ? 0 : $requestData->applicable_ncb),
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
                        'segment_id' => 0,
                        'rto_cluster_id' => 0,
                        'car_age' => $vehicle_age,
                        'aai_discount' => 0,
                        'ic_vehicle_discount' => '',
                    ],
                    'basic_premium' => $covers['od'],
                    'deduction_of_ncb' => $covers['ncb'],
                    'tppd_premium_amount' => $covers['tp'],
                    'tppdDiscount'          => 0,
                    'motor_electric_accessories_value' => $electrical_amount,//need included
                    'motor_non_electric_accessories_value' => $non_electrical_amount,//need included
                    'motor_lpg_cng_kit_value' => '0',//need included
                    'cover_unnamed_passenger_value' => $pa_unnamed,
                    'seating_capacity' => $mmv_data['seating_capacity'],
                    'default_paid_driver' => 0,
                    'motor_additional_paid_driver' => $pa_paid_driver,
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
                    'antitheft_discount' => 0,//need included
                    'final_od_premium' => $total_od_premium ?? 0,
                    'final_tp_premium' => $total_tp_premium ?? 0,
                    'final_total_discount' => $total_discount_premium,
                    'final_net_premium' => $total_base_premium ?? 0,
                    'final_gst_amount' => $premium['premium']['tax'] ?? 0,
                    'final_payable_amount' => $premium['premium']['payble'] ?? 0,
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

        }
        else{
            return  [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium_amount'    => '0',
                'status'            => false,
                'message'           => 'Bike Insurer Not found',
            ];
        }
    }
    catch(Exception $e){
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

function nic_mmv_check($mmv){
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

    if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle Not Mapped',
            ]
        ]);
    } elseif ($mmv->ic_version_code == 'DNE') {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]
        ]);
    }else{
        return ['status' => true, 'mmv_data' => $mmv ];
    }
}

function create_customer($customer, $enquiryId, $productData){

    $is_individual = ($customer['type'] == 'Individual') ? true : false;

    $name = explode(' ', $customer['first_name']);
    $name[1] = (isset($name[1]) ? $name[1] : '');

    $createCustomerRequest = [
        "address"               =>  $customer['address'],
        "customerType"          =>  $customer['type'],
        "dateOfBirth"           =>  ($is_individual ? date("d-m-Y", $customer['dob']) : ''),
        "gender"                =>  $customer['gender'],
        "mobileNo"              =>  $customer['mobile'],
        "paidupCapital"         =>  ($is_individual ? '' : '500000000'),
        "primaryEmail"          =>  $customer['email'],
        "title"                 =>  $customer['salutation'],
        "pan"                   =>  $customer['pan_no'],
        "gstin"                 =>  $customer['gstin'],

        "pinCd"                 =>  $customer['pincode_details']['data']['id'],
        "stateName"             =>  $customer['pincode_details']['data']['state_id'],
        "cityName"              =>  $customer['pincode_details']['data']['city_id'],
        "districtName"          =>  $customer['pincode_details']['data']['district_id'],

        "occupation"            =>  $customer['occupation'],
        "maritalStatus"         =>  $customer['marital_status'],

        "corporateName"         =>  (!$is_individual ? $customer['last_name'] : ''),
        "industryType"          =>  ($is_individual ? '' : '19211901'),
        "organizationType"      =>  ($is_individual ? '' : '11'),

        'firstName'         => ($is_individual ? $customer['first_name'] : $name[0]),
        'lastName'          => ($is_individual ? $customer['last_name'] : $name[1]),

        "mobileNo2"                   =>  "",
        "faxNumber"                   =>  "",
        "faxSTDCode"                  =>  "",
        "middleName"                  =>  "",
        "aadharNumber"                =>  "",
        "contactPerson"               =>  "",
        "telephoneNumber"             =>  "",
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
        "beatCode"                    =>  "18192",
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
        'headers'            =>[
            'content-type'      => 'text/plain',
          'WWW-Authenticate'  => config('constants.IcConstants.nic.AUTH_KEY_NIC_MOTOR')
        ]
    ];

    $get_response = getWsData(config('constants.IcConstants.nic.END_POINT_URL_NIC_CUSTOMER_CREATION'), $createCustomerRequest, 'nic', $additional_data);
    $createCustomerResponse=$get_response['response'];

    if($createCustomerResponse)
    {
        $str["from"]   = strpos($createCustomerResponse, "[");
        $str["to"]     = strpos($createCustomerResponse, "]");
        $str["rem"]    = $str["to"] - $str["from"];
        $str["customer_id"] = substr($createCustomerResponse, $str["from"]+1, $str["rem"]-1);

        if($str["customer_id"])
        {
            return [
                "status"        => 'true',
                "customer_id"   => $str["customer_id"],
            ];
        }
        else
        {
            return [
                "status"    => 'false',
                "message"   => $createCustomerResponse,
                "customer_id"   => '',
            ];
        }
    }
    else
    {
        return [
            'webservice_id'=>$get_response['webservice_id'],
            'table'=>$get_response['table'],
            "status"    => 'false',
            "message"   => 'insurer not reachable',
            "customer_id"   => '',
        ];
    }
}

