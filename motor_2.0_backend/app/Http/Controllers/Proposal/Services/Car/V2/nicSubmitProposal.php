<?php

namespace App\Http\Controllers\Proposal\Services\Car\V2;

use Config;
use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use Illuminate\Support\Str;
use App\Models\UserProposal;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use App\Models\MasterPremiumType;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use App\Models\PreviousInsurerList;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\NicVehicleColorMaster;
use App\Models\NicPrevCompBranchMaster;
use App\Http\Controllers\SyncPremiumDetail\Car\NicPremiumDetailController;
use App\Services\MMVDetailsService;


include_once app_path() . '/Helpers/CarWebServiceHelper.php';

class nicSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function submit($proposal, $request)
    {
        $enquiryId = customDecrypt($request['userProductJourneyId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();

        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $is_package = in_array($premium_type, ['comprehensive', 'breakin']);
        $is_liability = in_array($premium_type, ['third_party', 'third_party_breakin']);
        $is_od = in_array($premium_type, ['own_damage', 'own_damage_breakin']);
        $is_individual = $requestData->vehicle_owner_type == 'I';
        $is_new = !in_array($requestData->business_type, ['rollover', 'breakin']);
        $is_not_sure_case = $requestData->previous_policy_type == 'Not sure';

        $is_breakin = strpos($requestData->business_type, 'breakin') !== false && ($is_liability || $requestData->previous_policy_type != 'Third-party');

        $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);

        $quote_log_data = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->first();

        $idv = $quote_log_data->idv;

        // $mmv = get_mmv_details($productData, $requestData->version_id, 'national');
        $manufacture_date = $requestData->manufacture_year;
        $carbonDate = Carbon::createFromFormat('m-Y', $manufacture_date);
        $manufacture_year = $carbonDate->year;

        $mmvService = app(MMVDetailsService::class);
        $mmv = $mmvService->get_mmv_details($productData, $requestData->version_id, 'national', $manufacture_year);

        if ($mmv['status'] == 1) {
            $mmv_data = $mmv['data']['nic_mmv_details'];
            $mmv_versioncode = $mmv['data']['ic_version_code'];
        } else {
            return    [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }
        $mmv = (object) array_change_key_case((array) $mmv_data, CASE_LOWER);

        if (empty($mmv_versioncode) || $mmv_versioncode == '') {
            return camelCase([
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
            ]);
        } elseif ($mmv_versioncode == 'DNE') {
            return camelCase([
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]);
        }
        $mmv->idv = (string)($idv);
        $idv2 = $mmv->idv_1_2_yr;
        $idv3 = $mmv->idv_2_3_yr;
        $fuel = $mmv->nic_fuel_type_code;

        $mmv_data = [
            'manf_name'             => $mmv->make_name,
            'model_name'            => $mmv->model_name,
            'version_name'          => $mmv->variant_name,
            'seating_capacity'      => $mmv->seating_capacity,
            'carrying_capacity'     => $mmv->seating_capacity - 1,
            'cubic_capacity'        => $mmv->cubic_capacity,
            'fuel_type'             => $mmv->fuel_type,
            'gross_vehicle_weight'  => '',
            'vehicle_type'          => 'CAR',
            'version_id'            => $mmv_versioncode,//$mmv->ic_version_code,// on doubt
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

        $customer_type = $requestData->vehicle_owner_type == "I" ? "Individual" : "organization";

        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age = floor($age / 12);

        $vehicle_in_90_days = 0;

        $motor_manf_date = '01-' . $requestData->manufacture_year;

        $current_date = date('Y-m-d');

        if ($is_new) {
            $policy_start_date = date('d-m-Y');
            $policy_end_date = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 3 year'));
        } else {
            if ($requestData->business_type == "breakin") {
                $policy_start_date = date('Ymd');
            } else {
                $policy_start_date = date('Ymd', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));

                if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)) {
                    $policy_start_date = date('Ymd');
                }
            }
            $policy_end_date = date('Ymd', strtotime($policy_start_date . ' - 1 days + 1 year'));
        }

        if ($proposal->vehicale_registration_number == 'NEW') {
            $vehicle_register_no = 'NEW';
        } else {
            $vehicle_register_no = explode('-', $proposal->vehicale_registration_number);

            $vehicle_register_no = implode('-', $vehicle_register_no);

            $previousInsurerList    = PreviousInsurerList::where([
                'company_alias' => 'nic',
                'code' => $proposal->previous_insurance_company
            ])->first();

            if ($previousInsurerList) {
                $previousInsurerBranch = NicPrevCompBranchMaster::where('parent_id', $previousInsurerList->code)->first();
            }
        }

        // addon
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

        $Electricalaccess = $externalCNGKIT = $PAforUnnamedPassenger = $PAforaddionaldPaidDriver = $PAforaddionaldPassenger = $NonElectricalaccess = $PAPaidDriverConductorCleaner = $llpaidDriver = "N";

        // additional covers
        $externalCNGKITSI = $ElectricalaccessSI = $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI = $NonElectricalaccessSI = $PAPaidDriverConductorCleanerSI = $llpaidDriverSI = 0;

        $is_anti_theft = $is_voluntary_access = $autoMobileAssociation = $Electricalaccess = $NonElectricalaccess = $externalCNGKIT = $PAPaidDriverConductorCleaner = $PAforaddionaldPaidDriver = $PAforUnnamedPassenger = $llpaidDriver = false;
        $externalCNGKIT = "2";

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
                $externalCNGKIT                 = "1";
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
        $is_cpa = false;
        $tenure = '';
        if (isset($selected_addons->compulsory_personal_accident[0]['name'])) {
            $is_cpa = true;
            $tenure = isset($selected_addons->compulsory_personal_accident[0]['tenure']) ? $selected_addons->compulsory_personal_accident[0]['tenure'] : '1';
        } else {
            $cpa_cover = false;
            if ($customer_type == 'Individual') {
                $sapa['applicable'] = 'Y';
                if (isset($proposal_addtional_details['prepolicy']['reason'])) {
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
        }

        $VehicleCoverages_od = [];
        if ($Electricalaccess && $ElectricalaccessSI != '0') {
            $VehicleCoverages_od[] = [
                "ProductElementCode" => "B00813",
                "ManufactureYear" => date('Y-m-d', strtotime($motor_manf_date)),
                "ManufacturerSellingPrice" => $ElectricalaccessSI
            ];
        }

        if ($NonElectricalaccess && $NonElectricalaccessSI != '0') {
            $VehicleCoverages_od[] = [
                "ProductElementCode" => "B00814",
                "ManufactureYear" => date('Y-m-d', strtotime($motor_manf_date)),
                "ManufacturerSellingPrice" => $NonElectricalaccessSI
            ];
        }
        if ($externalCNGKIT && $externalCNGKITSI != '0') {
            $VehicleCoverages_od[] = [
                "ProductElementCode" => "B00815",
                "ManufactureYear" => date('Y-m-d', strtotime($motor_manf_date)),
                "ManufacturerSellingPrice" =>  $externalCNGKITSI
            ];
        }


        $VehicleCoverages_tp = [];

        if (!$is_individual) {
            $VehicleCoverages_tp[] = [
                "ProductElementCode" => "B00817",
                "CountCCC" => ((int) $mmv->seating_capacity) - 1
            ];
        }

        if ($llpaidDriver || !$is_individual) {
            $VehicleCoverages_tp[] = [
                "ProductElementCode" => "B00818",
                "CountCCC" => 1 //$mmv->seating_capacity
            ];
        }      

        $VehicleCoverages = [];
        if ($is_cpa) {
            $VehicleCoverages[] = [
                "ProductElementCode" => "B00811",
                "NumberOfYears" => $tenure,
                "NomineeName" => $proposal->nominee_name,
                "NomineeAge" => get_date_diff('year', $proposal->nominee_dob),
                "NomineeRelToProposer" => $proposal->nominee_relationship,
                "GuardianName" => "",
                "GuardianRelationshipwithNominee" => ""
            ];
        }
        if ($PAforaddionaldPaidDriver && $PAforaddionaldPaidDriverSI != '0') {
            $VehicleCoverages[] = [
                "ProductElementCode" => "B00821",
                "CountCCC" => 1,
                "SumInsured" => $PAforaddionaldPaidDriverSI
            ];
        }
        if ($PAforUnnamedPassenger && $PAforUnnamedPassengerSI != '0') {
            $VehicleCoverages[] = [
                "ProductElementCode" => "B00822",
                "CountCCC" => 1,
                "SumInsured" => $PAforUnnamedPassengerSI
            ];
        }
        $financierDetails = [];
        if($proposal->is_vehicle_finance == 1){
            $financierDetails[] = [
                "FNName" => $proposal->is_vehicle_finance ? $proposal->name_of_financer : '',
                "FNBranchName" => $proposal->is_vehicle_finance ? $proposal->hypothecation_city : '',
                "FNType" => $proposal->is_vehicle_finance,
            ];
        }
        $amount = [
            'electrical' => $ElectricalaccessSI,
            'non_electrical' => $NonElectricalaccessSI,
            'cng_kit' =>  $externalCNGKITSI,
            'pa_driver' => $PAPaidDriverConductorCleanerSI

        ];
        $zero_depreciation    = 'N';
        $consumable           = 'N';
        $loss_of_belongings   = 'N';
        $engine_secure        = 'N';
        $tyre_secure          = 'N';
        $key_replacement      = 'N';
        $road_side_assistance = 'N';
        $return_to_invoice    = 'N';
        $ncb_protector_cover  = 'N';
        $emi_protect          = 'N';

        foreach ($addons as $key => $value) {
            if (in_array('Zero Depreciation', $value) && $productData->zero_dep == 0) {
                $zero_depreciation = "Y";
            }
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
            if (in_array('NCB Protection', $value)) {
                $ncb_protector_cover = "Y";
            }
            if (in_array('EMI Protection', $value)) {
                $emi_protect = "Y";
            }
        }

        $is_pa_unnamed = (!$is_od && $PAforUnnamedPassenger) ? true : false;
        $is_pa_paid_driver = (!$is_od && $PAforaddionaldPaidDriver) ? true : false;
        $pa_named = false;

        $proposal_addtional_details = json_decode($proposal->additional_details, true);

        $is_sapa = false;
        $is_valid_license = 'Y';
        $sapa['applicable'] = 'N';
        $sapa['insured'] = '';
        $sapa['policy_no'] = '';
        $sapa['start_date'] = '';
        $sapa['end_date'] = '';

        if ($is_od) {
            $tp_insured = $proposal_addtional_details['prepolicy']['tpInsuranceCompany'];
            $tp_insurer_name = $proposal_addtional_details['prepolicy']['tpInsuranceCompanyName'];
            $tp_start_date = $proposal_addtional_details['prepolicy']['tpStartDate'];
            $tp_end_date = $proposal_addtional_details['prepolicy']['tpEndDate'];
            $tp_policy_no = $proposal_addtional_details['prepolicy']['tpInsuranceNumber'];

            $tp_insurer_address = DB::table('insurer_address')->where('Insurer', $tp_insurer_name)->first();
            $tp_insurer_address = keysToLower($tp_insurer_address);
        }

        $RegistrationNo_4 = $proposal_addtional_details['vehicle']['regNo3'];

        $proposal_addtional_details['vehicle']['regNo3'] = ((strlen($RegistrationNo_4) == 1) ? '000' . $RegistrationNo_4 : ((strlen($RegistrationNo_4) == 2) ? '00' . $RegistrationNo_4 : ((strlen($RegistrationNo_4) == 3) ? '0' . $RegistrationNo_4 : $RegistrationNo_4)));

        // addon
        $is_aa_apllicable = false;

        $insurer = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();
        $insurer = keysToLower($insurer);

        $proposal_salutation = ($is_individual ? (($proposal->gender == 'M') ? '1' : (($proposal->marital_status == 'M') ? '4' : '2')) : '_');

        $policy_date = [
            'start' => strtotime($policy_start_date),
            'end' => strtotime($policy_end_date)
        ];
        
        $prev_policy = [
            'end' => strtotime($requestData->previous_policy_expiry_date),
            'start' => strtotime('-1 year -1 day', strtotime($requestData->previous_policy_expiry_date))
        ];

        $tp_policy_date = [
            'start_date' => strtotime('+1 day', $policy_date['start']),
            'end_date' => strtotime('+3 year -1 day', strtotime('+1 day', $policy_date['start']))
        ];

        $yn_claim = 'Y';
        $ncb = [
            'current' => 0,
            'applicable' => 0,
            'active' => false,
            'level' => 0
        ];
        
        $ncb_levels = ['0' => '0', '20' => '1', '25' => '2', '35' => '3', '45' => '4', '50' => '5'];

        if ($requestData->is_claim === 'N') {
            $yn_claim = 'N';
            $ncb = [
                'active' => true,
                'current' => $requestData->previous_ncb,
                'applicable' => $requestData->applicable_ncb,
                'level' => $ncb_levels[$requestData->applicable_ncb] ?? '0'
            ];
        }
      
        if ($is_new) {
            $ncb['level'] = '0';
        }

        $pincode_data = nicSubmitProposal::get_state_city_from_pincode($proposal->pincode);
        $ckyc_meta_data = json_decode($proposal->ckyc_meta_data);
        $proposal_addtional_details = json_decode($proposal->additional_details);

        $customer = [
            'type' => ($is_individual ? 'IndiCustomer' : 'OrgCustomer'),
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
            'merchantId' => $ckyc_meta_data->merchantId,
            'signzyAppId' => $ckyc_meta_data->signzyAppId,
            'journeyType' => $ckyc_meta_data->JourneyType,
            'quote' => $enquiryId,
            'section' => 'car',
            'method' => 'Customer_creation - Proposal',
        ];
        if (!$is_individual) {
            $customer['organizationType'] = $proposal_addtional_details->owner->organizationType;
            $customer['industryType'] = $proposal_addtional_details->owner->industryType[0]->code;
        }

        $customer_data = nicSubmitProposal::create_customer($customer, $enquiryId, $productData);

        if ($customer_data['status'] == false) {
            return [
                "status"        => false,
                'webservice_id' => $customer_data['webservice_id'],
                'table'         => $customer_data['table'],
                "message"       => $customer_data['message'],
            ];
        } else {
            $customerNumber = $customer_data['customer_number'];
        }

        $plan_type = $is_od ? 'SAOD' : (($is_liability && $is_new) ? '0OD3TP' : ($is_liability ? '0OD1TP' : ($requestData->business_type == 'newbusiness' ? '1OD3TP' : '1OD1TP')));
        $vehicleBodyColor = "";
        if (!empty($proposal->vehicle_color)) {
            $vehicleBodyColor = NicVehicleColorMaster::where('color_name', $proposal->vehicle_color)->value('color_code');
        }
        // token Generation
        $token_response = self::generateToken($productData->product_name, $enquiryId);

        $quote_request_data['customer'] = [
            'policyId'      => '',
            'firstName'     => $proposal->first_name,
            'lastName'      => $proposal->last_name,
            'gender'        => ($is_individual ? ($proposal->gender == 'M' ? '1' : '2') : ''),
            'title'         => $proposal_salutation,
            'dateOfBirth'   => (int)($is_individual ? strtotime($proposal->dob) . '000' : ''),
            'maritalStatus' => ($is_individual ? ($proposal->marital_status == 'Single' ? '1' : '2') : ''),
            'language'      => '1010',
            'occupation'    => ($is_individual ? $proposal->occupation : ''),
            'mobile'        => $proposal->mobile_number,
            'email'         => $proposal->email,
            'addressLine'   => $proposal->address_line1 . ', ' . $proposal->address_line2,
            'city'          => $pincode_data['data']['city_id'],
            'district'      => $pincode_data['data']['district_id'],
            'state'         => $pincode_data['data']['state_id'],
            'country'       => '91',
            'postCode'      => (string)($proposal->pincode),
            'addressType'   => '2',
            'field01'       => $proposal->pan_number ?? '',
            'field02'       => '1',
            'field05'       => 'N',
        ];

        $quote_request_data['prev_policy'] = [
            0 => [
                'listId'                => '',
                'policyId'              => '',
                'policyNo'              => ($is_new ? '' : $proposal->previous_policy_number),
                'policyFrom'            => ($is_new ? '' : $prev_policy['start'] . '000'),
                'policyTo'              => ($is_new ? '' : $prev_policy['end'] . '000'),
                'prevCompanyName'       => ($is_new ? '' : (!empty($previousInsurerList->code) ? $previousInsurerList->code : '')),
                'companyBranch'         => ($is_new ? '' : (!empty($previousInsurerBranch->company_branch) ? $previousInsurerBranch->company_branch : '')),
                'premiumPaid'           => ($is_new ? '' : 3400),
                'noClaims'              => ($is_new ? '' : ($yn_claim == 'N' ? 0 : 1)),
                'totClaimsIncurr'       => ($is_new ? '' : ($yn_claim == 'N' ? 0 : 1)),
                'claimIncurredRatio'    => ($is_new ? '' : ($yn_claim == 'N' ? 0 : 1)),
            ],
        ];

        $quote_array = [
            "ProductCode" => "PC",
            "ProductVersion" => "1.0",
            "EffectiveDate" => date('Y-m-d', $policy_date['start']),
            "ExpiryDate" => date('Y-m-d', $policy_date['end']),
            "ChannelType" => config('IC.NIC.V2.CAR.NIC_CHANNEL_TYPE'),
            "AgreementCode" => config('IC.NIC.V2.CAR.AGREEMENT_CODE_NIC_MOTOR'),
            "PlanType" => $plan_type,
            "PolicyCustomerList" => [
                [
                    "CustomerNumber" => $customerNumber
                ]
            ],
            "FinancierDetailsList" => $financierDetails,
            "PolicyLobList" => [
                [
                    "ProductCode" => "PC",
                    "PolicyRiskList" => [
                        [
                            "ProductElementCode" => "R00004",
                            "VehicleCategory" => $is_new ? "1" : "2",
                            "DateOfDeliveryPurchase" => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                            "RegistrationDate" => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                            "RegistrationNo" => $is_new ? 'NEW-0001' : $vehicle_register_no,
                            "BharathSeries" => "2",
                            "EngineNo" => $proposal->engine_number,
                            "ChassisNo" => $proposal->chassis_number,
                            "RTONameCode" => str_replace('-', '', $requestData->rto_code),
                            "Variant" => $mmv->serial_no,
                            "FirstRegisteredOwner" => $is_new ? "1" : "2",
                            "IsExternalCNG_LPGKit" => $externalCNGKIT,
                            "ManufactureYear" => explode('-',$requestData->manufacture_year)[1],
                            "VehicleColor" => $vehicleBodyColor,
                            "BodyType" => "1",
                            "TPPD_SILimit" => "4",//$is_tppd ? "1" : "2",
                            "IsNilDeporNilDepPlusOpted" => $zero_depreciation == "Y" ? "1" : "0", //"0 if not opted, 1 if NilDep opted, 2 if NilDepPlus opted 
                            "IsNCBProtectOpted" => $ncb_protector_cover == "Y" ? "1" : "2",
                            "IsRSAOpted" => $road_side_assistance == "Y" ? "1" : "2",
                            "IsInvoiceProtectOpted" => $return_to_invoice == "Y" ? "1" : "2",
                            // "IsFuelFlipOpted" => "1",
                            // "IsPickDropOpted" => "1",
                            // "IsDailyAllowanceOpted" => "1",
                            "IsEngineProtectOpted" => $engine_secure == "Y" ? "1" : "2",
                            "IsConsumablesProtectOpted" => $consumable == "Y" ? "1" : "2",
                            // "IsLossofDLOpted" => "1",
                            "LockkeySI" => $key_replacement == "Y" ? "5000" : "0",
                            "EMIProtect_EMIAmount" => $emi_protect == "Y" ? "10000" : "0",
                            "LossOfBelongingSI" => $loss_of_belongings == "Y" ? "20000" : "0",
                            "TyreAndRimSI" => $tyre_secure == "Y" ? "200000" : "0",
                            "IsAAMember" => "",
                            "NameOfAssociation" => "",
                            "AAMemberNo" => "",
                            "AAMemberExpiryDate" => "",
                            "ActiveLiabilityPolicyNo" => $is_od ? $tp_policy_no : "",
                            "ActiveLiabilityPolicyEffDate" => $is_od ? $tp_start_date : "",
                            "ActiveLiabilityPolicyExpDate" => $is_od ? $tp_end_date : "",
                            "ActiveLiabilityPolicyInsurer" => "",
                            "ActiveLiabilityPolicyInsurerBranch" => "",
                            // "PIRNNo" => "ID1234",
                            // "PIRN_Date" => "2024-05-15T10:00",
                            // "PIRN_UserID" => "XXYYZZ",
                            // "PIRN_Decision" => "Approved",
                            // "PIRN_DecisionSubmitDate" => "2024-05-17T10:00",
                            // "PIRN_Remarks" => "No Damages Observed",
                            "BasicIDV" => $idv,
                            "PolicyCoverageList" => [
                                [
                                    "ProductElementCode" => "C0003006",
                                    "PolicyBenefitList" => $VehicleCoverages_od
                                ],
                                [
                                    "ProductElementCode" => "C0003007",
                                    "PolicyBenefitList" => $VehicleCoverages_tp
                                ],
                                [
                                    "ProductElementCode" => "C0003008",
                                    "PolicyBenefitList" => $VehicleCoverages
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "PreviousInsuranceDetailsList" => [
                [
                    "PrevInsuranceCompanyBranchId" => $previousInsurerBranch->role_id ?? '',
                    "PrevInsuranceCompanyId" => ($is_new ? '' : (!empty($previousInsurerList->code) ? $previousInsurerList->code : '')),
                    "PrePolicyNo" => ($is_new ? '' : $proposal->previous_policy_number),
                    "PrePolicyStartDate" => $is_not_sure_case ? null : date('Y-m-d', $prev_policy['start']),
                    "PrePolicyEndDate" => $is_not_sure_case ? null : date('Y-m-d', $prev_policy['end']),
                    "NCB" => $ncb['current'],
                    "NoOfClaims" => ($yn_claim == 'N' ? 0 : 1),
                    "PremiumPaid" => "0",
                    "ClaimIncurredRatio" => "0",
                    "TotClaimsIncurr" => "0"
                ]
            ],
            "DiscountDetailsList" => [
                [
                    "CoverageId" => "C0003006",
                    "DiscountType" => "104",
                    "DiscountPercent" => "0"
                ]
            ]
        ];

        if ($requestData->business_type == 'newbusiness') {
            $quote_array['PreviousInsuranceDetailsList'] = [];
        }
        foreach ($quote_array['PolicyLobList'] as &$lob) {
            foreach ($lob['PolicyRiskList'] as &$risk) {
                //in case of OD unseting C0003007 and C0003008 CoverCode
                if ($is_od) {
                    foreach ($risk['PolicyCoverageList'] as $key => $coverage) {
                        if (in_array($coverage['ProductElementCode'], ['C0003007', 'C0003008'])) {
                            unset($risk['PolicyCoverageList'][$key]);
                        }
                    }
                }
                // unseting C0003008 CoverCode if PolicyBenefitList is empty
                foreach ($risk['PolicyCoverageList'] as $key => $coverage) {
                    if ($coverage['ProductElementCode'] == 'C0003008' && empty($coverage['PolicyBenefitList'])) {
                        unset($risk['PolicyCoverageList'][$key]);
                    }
                }
                // in case of TP unseting C0003006 CoverCode
                if ($is_liability) {
                    foreach ($risk['PolicyCoverageList'] as $key => $coverage) {
                        if ($coverage['ProductElementCode'] === 'C0003006') {
                            unset($risk['PolicyCoverageList'][$key]);
                        }
                    }
                }
                $risk['PolicyCoverageList'] = array_values($risk['PolicyCoverageList']);
            }
        }

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Proposal Submition - Proposal',
            'transaction_type'  => 'proposal',
            'productName'       => $productData->product_name,
            'content_type'      => 'application/json',
            'headers' => [
                'Content-Type'      => 'application/json',
                'User-Agent'        => $_SERVER['HTTP_USER_AGENT'],
                'Authorization'     => 'Bearer ' . $token_response['token']
            ]
        ];

        $get_response = getWsData(config('IC.NIC.V2.CAR.END_POINT_URL_NIC_MOTOR_PROPOSAL_PREMIUM'), $quote_array, 'nic', $additional_data);

        $response = $get_response['response'];
        $xhdf = ($response) ? 'true' : 'false';

        if ($response) {
            $response = json_decode($response, true);

            if (!empty($get_response['response'])) {
                $response = json_decode($get_response['response'], true);
                $msg = '';
                if (!empty($response['messages']) && is_array($response['messages'])) {
                    $msg = implode(" | ", array_column($response['messages'], 'message'));
                } elseif (!empty($response['error'])) {
                    $msg = $response['error'];
                }
                if (!empty($msg)) {
                    return [
                        'webservice_id'  => $get_response['webservice_id'] ?? null,
                        'table'          => $get_response['table'] ?? null,
                        'status'         => false,
                        'msg'            => $msg,
                        'quote_request'  => $response
                    ];
                }
            }

            $tax = ($response['PolicyObject']['CGST'] ?? 0) + ($response['PolicyObject']['SGST'] ?? 0);

            $cover_codes = [
                'C0003009' => 'return_to_invoice',
                'C0003015' => 'rsa',
                'C0003003' => 'zero_dep',
                'C0003010' => 'engine_protect',
                'C0003012' => 'key_replace',
                'C0003011' => 'consumable',
                'C0003014' => 'Loss_of_belonging',
                'C0001004' => 'tyre_secure',
                'C0003017' => 'ncb_protection',
                // 'C0003006' => 'own_damage',
                'B00817' => 'legal_liability_to_employee',
                'B00818'   => 'legal_liability_driver_cleaner',
                'B00823'   => 'third_party_basic',
                'B00811'   => 'compulsory_pa',
                'B00813'   => 'electrical_accessories',
                'B00814'   => 'non_electrical_accessories',
                'B00815'   => 'cng_kit',
                'B00816'   => 'lpg_kit',
                'B00812'   => 'own_damage_basic',
                'B00821'   => 'optional_pa_paid_driver_cleaner',
                'B00822'   => 'optional_unnamed_persons',
            ];

            $covers = [
                'return_to_invoice'             => 0,
                'rsa'                           => 0,
                'zero_dep'                      => 0,
                'engine_protect'                => 0,
                'key_replace'                   => 0,
                'consumable'                    => 0,
                'Loss_of_belonging'             => 0,
                'tyre_secure'                   => 0,
                'ncb_protection'                => 0,
                // 'own_damage'                    => 0,
                'compulsory_pa'                 => 0,
                'electrical_accessories'        => 0,
                'non_electrical_accessories'    => 0,
                'cng_kit'                       => 0,
                'lpg_kit'                       => 0,
                'own_damage_basic'              => 0,
                'legal_liability_driver_cleaner' => 0,
                'third_party_basic'              => 0,
                'optional_pa_paid_driver_cleaner' => 0,
                'optional_unnamed_persons'      => 0,
                'legal_liability_to_employee'   => 0
            ];

            foreach ($response['PolicyObject']['PolicyLobList'] as $lob) {
                foreach ($lob['PolicyRiskList'] as $risk) {
                    foreach ($risk['PolicyCoverageList'] as $coverage) {
                        $productElementCode = $coverage['ProductElementCode'];
                        if (isset($cover_codes[$productElementCode])) {
                            $key = $cover_codes[$productElementCode];
                            $covers[$key] = $coverage['GrossPremium'];
                        }
                        if (isset($coverage['PolicyBenefitList'])) {
                            foreach ($coverage['PolicyBenefitList'] as $benefit) {
                                $benefitCode = $benefit['ProductElementCode'];
                                if (isset($cover_codes[$benefitCode])) {
                                    $key = $cover_codes[$benefitCode];
                                    $covers[$key] = $benefit['GrossPremium'];
                                }
                            }
                        }
                    }
                }
            }

            $covers['ncb'] = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['NCBAmount'] ?? 0;
            $index = ($is_breakin || $is_liability) ? 0 : 1;
            $lpg_cng_tp_amount = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index]['CNGLPGKitLiabilityPremium'] ?? 0;
            $AADiscountAmount = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['AADiscountAmount'] ?? 0;
            $premium['premium']['tax']      = $tax;
            $premium['premium']['total']    = $response['PolicyObject']['BeforeVatPremium'];
            // $premium['premium']['payble']   = $response['premiumBifurcation']['actualPremiumPayable'];

            $total_od_premium           =  $covers['own_damage_basic'] + $covers['electrical_accessories'] + $covers['non_electrical_accessories'] + $covers['cng_kit'] + $covers['lpg_kit'] - $AADiscountAmount;

            $total_add_ons_premium      = $covers['rsa'] + $covers['return_to_invoice'] + $covers['zero_dep'] + $covers['engine_protect'] + $covers['key_replace'] + $covers['consumable'] + $covers['Loss_of_belonging'] + $covers['tyre_secure'] + $covers['ncb_protection'];

            $total_tp_premium           = $covers['third_party_basic'] + $covers['legal_liability_driver_cleaner'] + $covers['optional_unnamed_persons'] + $covers['optional_pa_paid_driver_cleaner'] + $covers['compulsory_pa'] + $covers['legal_liability_to_employee'] + $lpg_cng_tp_amount;

            $total_discount_premium     = $covers['ncb'];
            $basePremium = $total_od_premium + $total_tp_premium +  $total_add_ons_premium - $total_discount_premium;
            $totalTax = $basePremium * 0.18;
            $final_premium = $basePremium + $totalTax;
            $proposal->proposal_no = $response['PolicyObject']['ProposalNo'];
            $proposal_addtional_details = json_decode($proposal->additional_details, true);
            $proposal_addtional_details['nic']['customer_number'] = $customerNumber;
            $proposal_addtional_details['nic']['proposal_no'] =  $response['PolicyObject']['ProposalNo'];

            //$final_total_discount               = $total_discount_premium;
            $proposal->ncb_discount             = $total_discount_premium;
            $proposal->final_payable_amount     = $response['PolicyObject']['DuePremium'];
            $proposal->policy_start_date        = Carbon::parse($policy_start_date)->format('d-m-Y');
            $proposal->policy_end_date          = Carbon::parse($policy_end_date)->format('d-m-Y');
            $proposal->od_premium               = $total_od_premium;
            $proposal->tp_premium               = $total_tp_premium;
            $proposal->cpa_premium              = $covers['compulsory_pa'];
            $proposal->addon_premium            = $total_add_ons_premium;
            $proposal->ncb_discount             = $covers['ncb'];
            $proposal->service_tax_amount       = $premium['premium']['tax'];
            $proposal->total_premium            = $premium['premium']['total'];
            $proposal->discount_percent         = ($is_liability ? 0 : $requestData->applicable_ncb);

            $proposal->additional_details       = $proposal_addtional_details;
            $proposal->save();

            $data['user_product_journey_id']    = customDecrypt($request['userProductJourneyId']);
            $data['ic_id']                      = $productData->policy_id;
            $data['stage']                      = 'Proposal Accepted';
            $data['proposal_id']                = $proposal->user_proposal_id;

            NicPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

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

    public static function generateToken($productName, $enquiryId, $transactionType = 'proposal')
    {
        $tokenParam = [
            "username" => config('IC.NIC.V2.CAR.USERNAME_NIC_MOTOR'),
            "password" => config('IC.NIC.V2.CAR.PASSWORD_NIC_MOTOR')
        ];

        $get_response = getWsData(config('IC.NIC.V2.CAR.NIC_TOKEN_GENERATION_URL_MOTOR'), $tokenParam, 'nic', [
            'requestMethod' => 'post',
            'type' => 'tokenGeneration',
            'section' => 'car',
            'productName' => $productName,
            'enquiryId' => $enquiryId,
            'method' => 'Token Generation',
            'transaction_type' => $transactionType,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => $_SERVER['HTTP_USER_AGENT']
            ]
        ]);
        $tokenResponse = $get_response['response'];

        if ($tokenResponse && $tokenResponse != '' && $tokenResponse != null) {
            $tokenResponse = json_decode($tokenResponse, true);

            if (!empty($tokenResponse)) {
                if (isset($tokenResponse['error'])) {
                    return [
                        'status'    => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg'       => $tokenResponse['error'],
                        'stage'     => 'token'
                    ];
                } else {
                    return [
                        'status'    => true,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'token'     => $tokenResponse['access_token'],
                        'stage'     => 'token'
                    ];
                }
            } else {
                return [
                    'status'    => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'msg' => 'Error in token generation service',
                    'stage' => 'token'
                ];
            }
        } else {
            return [
                'status'    => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg'       => 'Insurer Not Reachable',
                'stage'     => 'token'
            ];
        }
    }

    public static function create_customer($customer, $enquiryId, $productData)
    {

        $is_individual = ($customer['type'] == 'IndiCustomer') ? true : false;

        $name = explode(' ', $customer['first_name']);
        $name[1] = (isset($name[1]) ? $name[1] : '');
        // token Generation
        $token_response = self::generateToken($productData->product_name, $enquiryId);

        $is_individual = ($customer['type'] == 'IndiCustomer') ? true : false;
        if ($is_individual) {
            $createCustomerRequest = [
                "CustomerType" => $customer['type'],
                "IdType" => "1",
                "IdNumber" => $customer['signzyAppId'], //Pass Signzy ID returned from KYC Call
                "Title" => $customer['salutation'],
                "FirstName" => ($is_individual ? $customer['first_name'] : $name[0]),
                "MiddleName" => "",
                "LastName" => ($is_individual ? $customer['last_name'] : $name[1]),
                "DateOfBirth" => ($is_individual ? date("Y-m-d", $customer['dob']) : ''),
                "LanguagePreferred" => "",
                "IsHandicappedMod" => "",
                "PartyStatus" => "1",
                "Territory" => "",
                "AadharNumber" => "",
                "CustomerGSTINNo" => $customer['gstin'],
                "Sector" => "",
                "Gender" => ($customer['gender'] == 'M') ? '1' : '2',
                "MaritalStatusCode" => ($is_individual ? ($customer['marital_status'] == 'Single' ? '1' : '2') : ''),
                "Mobile" =>  $customer['mobile'],
                "Email" => $customer['email'],
                "ConsentEmail" => "",
                "OccupationType" => "1001", //$customer['occupation'],
                "Address" => $customer['address'],
                "District" => $customer['pincode_details']['data']['district_id'],
                "City" => $customer['pincode_details']['data']['city_id'],
                "State" => $customer['pincode_details']['data']['state_id'],
                "PostCode" => $customer['pincode_details']['data']['pin_cd'],
                "PinSINo" => $customer['pincode_details']['data']['sl_no'],
                "BeatCode" => $customer['pincode_details']['data']['beat_code'],
                "Country" => "91",
                "PANNumber" => $customer['pan_no'],
                "TrackingNo" => "WA",
                "Creator" => "91034800000001",
                "MerchantId" => $customer['merchantId'],
                "JourneyType" => $customer['journeyType'],
                "PANStatusCode" => "",
                "CKYCNo" => "",
                "AddressType" => "2"
            ];
        } else {
            $createCustomerRequest = [
                "CustomerType" => $customer['type'],
                "IdType" => "1",
                "IdNumber" => $customer['signzyAppId'], //Pass Signzy ID returned from KYC Call
                "CorporateName" =>  $customer['first_name'],
                "PaidupCapital" => "100000",
                "IndustryType" => $customer['industryType'],
                "OrganizationType" => $customer['organizationType'],
                "PartyStatus" => "1",
                "Website" => "",
                "RegisrationNo" => "",
                "RegistrationExpiryDate" => "",
                "DateOfRegistration" => "1999-09-23T00:00:00",
                "CustomerGSTINNo" => $customer['gstin'],
                "PANNumber" => $customer['pan_no'],
                "RegistrationAuthority" => "",
                "CustomerSegment" => "",
                "ContactName" => "",
                "PostCode" => $customer['pincode_details']['data']['pin_cd'],
                "PinSINo" => $customer['pincode_details']['data']['sl_no'],
                "BeatCode" => $customer['pincode_details']['data']['beat_code'],
                "Address" => $customer['address'],
                "District" => $customer['pincode_details']['data']['district_id'],
                "City" => $customer['pincode_details']['data']['city_id'],
                "State" => $customer['pincode_details']['data']['state_id'],
                "Mobile" =>  $customer['mobile'],
                "Email" => $customer['email'],
                "Country" => "91",
                "TrackingNo" => "WA",
                "Creator" => "91034800000001",
                "MerchantId" => $customer['merchantId'],
                "JourneyType" => $customer['journeyType'],
                "PANStatusCode" => "",
                "CKYCNo" => "",
                "AddressType" => "3",
            ];
        }

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Customer Creation - Proposal',
            'transaction_type'  => 'proposal',
            'productName'       => $productData->product_name,
            'content_type'      => 'application/json',
            'headers' => [
                'Content-Type'      => 'application/json',
                'User-Agent'        => $_SERVER['HTTP_USER_AGENT'],
                'Authorization'     => 'Bearer ' . $token_response['token']
            ]
        ];

        $URL = $is_individual ? config('IC.NIC.V2.CAR.INDIVIDUAL.END_POINT_URL_NIC_CUSTOMER_CREATION') : config('IC.NIC.V2.CAR.CORPORATE.END_POINT_URL_NIC_CUSTOMER_CREATION');
        $get_response = getWsData($URL, $createCustomerRequest, 'nic', $additional_data);

        $createCustomerResponse = $get_response['response'];

        if ($createCustomerResponse) {
            $responseData = json_decode($createCustomerResponse, true);
            if ($responseData && isset($responseData['data']['CustomerNumber']) && $responseData['success'] == 'true') {
                $customerNumber = $responseData['data']['CustomerNumber'];
                $CustomerId = $responseData['data']['CustomerId'];
                return [
                    "status"        => true,
                    'webservice_id' => $get_response['webservice_id'],
                    'table'         => $get_response['table'],
                    "customer_id"   => $CustomerId,
                    "customer_number" => $customerNumber
                ];
            } else {
                return [
                    "status"        => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table'         => $get_response['table'],
                    "message"       => $responseData['message'],
                    "customer_id"   => '',
                    "customer_number" => '',
                ];
            }
        } else {
            return [
                "status"        => false,
                'webservice_id' => $get_response['webservice_id'],
                'table'         => $get_response['table'],
                "message"       => 'insurer not reachable',
                "customer_id"   => '',
                "customer_number" => '',
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
}
