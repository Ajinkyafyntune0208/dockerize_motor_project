<?php

namespace App\Http\Controllers\Proposal\Services;

use App\Http\Controllers\SyncPremiumDetail\Services\UnitedIndiaPremiumDetailController;
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
use App\Models\unitedIndiaWithNcbDiscountGridCv;
use App\Models\unitedIndiaWithoutNcbDiscountGridCv;
use App\Models\unitedIndiaRtoCityDiscountCv;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

class UnitedIndiaSubmitProposal
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
        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $is_package       = (($premium_type == 'comprehensive') ? true : false);
        $is_liability     = (($premium_type == 'third_party') ? true : false);
        $is_od            = (($premium_type == 'own_damage') ? true : false);
        $is_individual    = (($requestData->vehicle_owner_type == 'I') ? true : false);
        $is_breakin     = (((strpos($requestData->business_type, 'breakin') === false) || (!$is_liability && $requestData->previous_policy_type == 'Third-party')) ? false : true);

        $is_zero_dep        = ($productData->zero_dep  == 0) ? true : false;

        $quote_log_data = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->first();

        $idv = $quote_log_data->idv;

        $mmv = get_mmv_details($productData, $requestData->version_id, 'united_india');

        $parent_id = get_parent_code($productData->product_sub_type_id) ;

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

        if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
            return camelCase([
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
            ]);
        } elseif ($mmv->ic_version_code == 'DNE') {
            return camelCase([
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]);
        }
        $mmv->idv       = $idv;

        $rto_code       = $requestData->rto_code;

        $rto_data       = DB::table('united_india_rto_master')->where('TXT_RTA_CODE', strtr($rto_code, ['-' => '']))->first();
        $rto_data = keysToLower($rto_data);
        if (empty($rto_data)) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'RTO not available',
                'request' => [
                    'rto_code' => $requestData->rto_code,
                    'rto_data' => $rto_data
                ]
            ];
        }

        $product_code = config('constants.IcConstants.united_india.CV.PRODUCT_CODE');
        $bus_category = '';

        if($parent_id == 'PCV' && $mmv->carryingcapacity <= 6){
            $sub_product_code = config('constants.IcConstants.united_india.CV.C1A.SUB_PRODUCT_CODE');
        }
        
        if ($parent_id == 'PCV' && $mmv->carryingcapacity > 6) {
            $sub_product_code = config('constants.IcConstants.united_india.CV.C1B.SUB_PRODUCT_CODE');

            $bus_category = 'Others';
        }

        if ($parent_id == 'GCV') {
            $sub_product_code = config('constants.IcConstants.united_india.CV.GCV.SUB_PRODUCT_CODE');
        }

        if($productData->product_sub_type_id == 10){
            $bus_category = 'School Bus';
        }

        $customer_type  = $requestData->vehicle_owner_type == "I" ? "Individual" : "organization";

        $btype_code     = $requestData->business_type == "rollover" ? "2" : "1";
        $btype_name = ($requestData->business_type == "rollover" || $is_breakin) ? "Roll Over" : "New Business";

        $vehicleDate    = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1          = new DateTime($vehicleDate);
        $date2          = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval       = $date1->diff($date2);
        $age            = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age    = floor($age / 12);

        $vehicle_in_90_days = 0;

        $motor_manf_date = '01-' . $requestData->manufacture_year;
        $is_new  = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

        $current_date       = date('Y-m-d');

        if ($requestData->business_type == "newbusiness") {
            $policy_start_date  = date('d-m-Y');
            $policy_end_date    = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 1 year'));
            $tp_start_date =  $policy_start_date;
            $tp_end_date   = $policy_end_date;
        } else {
            $policy_start_date  = date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));

            if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)) {
                $policy_start_date = date('d-m-Y', strtotime('+1 day', time()));
            }

            $policy_end_date    = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 1 year'));
            $tp_start_date =  $policy_start_date;
            $tp_end_date   = $policy_end_date;
        }

        $vehicle_register_no    = explode('-', $proposal->vehicale_registration_number);
        $previousInsurerList    = PreviousInsurerList::where([
            'company_alias' => 'united_india',
            'code' => $proposal->previous_insurance_company
        ])->first();

        // addon
        $RepairOfGlasPlastcFibNRubrGlas = $DepreciationReimbursement = $NoOfClaimsDepreciation = $ConsumablesExpenses = $EngineSecure = $TyreSecure = $KeyReplacement = $RoadsideAssistance = $ReturnToInvoice = $NCBProtectionCover = $EmergTrnsprtAndHotelExpense = $ac_opted_in_pp = $PAforaddionalPaidDriver = $PAforaddionalPaidDriverSI = $NCBProtectionCover = "N";

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons_v2 = ($selected_addons->addons == null ? [] : $selected_addons->addons);
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $discounts              = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);


        $zero_dep                           = 'N';
        $consumable                         = 'N';
        $ncb_protection                     = False;
        $engine_secure                      = 'N';
        $tyre_secure                        = 'N';
        $key_replacement                    = 'N';
        $road_side_assistance               = 'N';
        $return_to_invoice                  = 'N';
        $yn_paid_driver                     = 'N';
        $imt_23                             = 'N';
        $addtowcharge                       = 0;
        $nfpp                               = false;
        $nfpp_si                            = 0;

        foreach ($addons_v2 as $key => $value) {
            if (in_array('Additional Towing', $value)) {
                $addtowcharge = isset($value['sumInsured']) ? $value['sumInsured'] : 20000;
            }
        }

        foreach ($addons as $key => $value) {
            if (in_array('Road Side Assistance', $value)) {
                $road_side_assistance = "Y";
            }
            if (in_array('Zero Depreciation', $value)) {
                $zero_dep = "Y";
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
            if (in_array('NCB Protection', $value)) {
                $ncb_protection = True;
            }
            if (in_array('IMT - 23', $value)) {
                $imt_23 = "Y";
            }
        }

        $zero_dep               = (!$is_liability && $is_zero_dep && ($zero_dep == 'Y')) ? -1 : 0;
        $consumable             = (!$is_liability && ($consumable == 'Y')) ? 'Y' : 'N';
        $engine_secure          = (!$is_liability && ($engine_secure == 'Y')) ? 'Y' : 'N';
        $key_replacement        = (!$is_liability && ($key_replacement == 'Y')) ? 'Y' : 'N';
        $tyre_secure            = (!$is_liability && ($tyre_secure == 'Y')) ? 'Y' : 'N';
        $road_side_assistance   = (!$is_liability && ($road_side_assistance == 'Y')) ? 'Y' : 'N';
        $return_to_invoice      = (!$is_liability && $is_zero_dep && ($return_to_invoice == 'Y')) ? 'Y' : 'N';
        $imt_23                 = (!$is_liability && ($imt_23 == 'Y') && ($parent_id == 'GCV' || ($parent_id == 'PCV' && $mmv->carryingcapacity > 6)) ? -1 : 0);
        $addtowcharge           = (!$is_liability && !empty($addtowcharge)) ? $addtowcharge : 0;
        
        $Electricalaccess = $ElectricalaccessSI = $externalCNGKIT = $PAforUnnamedPassenger = $PAforUnnamedPassengerSI = $externalCNGKITSI = $NonElectricalaccess = $NonElectricalaccessSI = $llpaidDriver = "N";
        $PAforaddionaldPaidDriver = $PAforaddionaldPaidDriverSI = $llpaidDriverCleanerSI = $llpaidDriverSI = $llpaidDriverConductorSI = 0;
        $externalCNGKITSI = 0;

        $geo_extn_countries = '';

        foreach ($accessories as $key => $value) {
            if (in_array('Electrical Accessories', $value)) {
                $Electricalaccess = "Y";
                $ElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('Non-Electrical Accessories', $value)) {
                $NonElectricalaccess = "Y";
                $NonElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                $externalCNGKIT = "Y";
                $externalCNGKITSI = $value['sumInsured'];
            }

        }

        foreach ($additional_covers as $key => $value) {
            if (in_array('PA cover for additional paid driver', $value)) {
                $PAforaddionaldPaidDriver = 1;
                $PAforaddionaldPaidDriverSI = $value['sumInsured'];
            }

            if (in_array('Unnamed Passenger PA Cover', $value)) {
                $PAforUnnamedPassenger = "Y";
                $PAforUnnamedPassengerSI = $value['sumInsured'];
            }

            if (in_array($value['name'], ['LL paid driver', 'LL paid driver/conductor/cleaner'])) {
                $llpaidDriver                   = "Y";
                $llpaidDriverCleanerSI          = $value['LLNumberCleaner'] ?? 0;
                $llpaidDriverSI                 = $value['LLNumberDriver'] ?? 0;
                $llpaidDriverConductorSI        = $value['LLNumberConductor'] ?? 0;
            }

            if(in_array('Geographical Extension', $value)){
                foreach($value['countries'] as $country){
                    if($country){
                        $geo_extn_countries .= $geo_extn_countries == '' ? $country : '/' . $country;
                    }
                }
            }

            if(in_array('NFPP Cover', $value)){
                $nfpp = true;
                $nfpp_si = $value['nfppValue'];
            }
        }
        $LLnumberSum = 0;
        $LLnumberSum = $llpaidDriverCleanerSI + $llpaidDriverSI + $llpaidDriverConductorSI;
        //discounts
        $is_limited_to_own_premises = 0;

        foreach ($discounts as $key => $discount){
            if (in_array('Vehicle Limited to Own Premises', $discount)) {
                $is_limited_to_own_premises = -1;
            }
        }
        
        // cpa vehicle

        $proposal_addtional_details = json_decode($proposal->additional_details, true);

        $driver_declaration    = "ODD01";

        $cpa_cover                          = (($is_individual) ? -1 : 0);
        $cpa_cover_period                   = (($is_individual) ? 1 : 0);
        // $txt_cover_period                   = (($is_individual && !$is_od) ? 1 : 0);
        $anti_theft_flag                    = ((!$is_liability && ($requestData->anti_theft_device == 'Y')) ? -1 : 0);
        $yn_paid_driver                     = (!$is_od && $is_individual && $llpaidDriver == "N") ? 'Y' : 'N';


        $is_sapa = false;
        $is_valid_license       = 'Y';
        $sapa['applicable']     = 'N';
        $sapa['insured']        = '';
        $sapa['policy_no']      = '';
        $sapa['start_date']     = '';
        $sapa['end_date']       = '';
        if (isset($selected_addons->compulsory_personal_accident[0]['name'])) {
            $cpa_cover = -1;
            $driver_declaration    = "ODD01";
        } elseif (isset($selected_addons->compulsory_personal_accident[0]['reason']) && $selected_addons->compulsory_personal_accident[0]['reason'] != "") {
            $cpa_cover = 0;
            if ($customer_type == 'Individual' && !$is_od) {
                $sapa['applicable'] = 'Y'; #$proposal_addtional_details['prepolicy']['reason']
                if ($selected_addons->compulsory_personal_accident[0]['reason'] == "I have another motor policy with PA owner driver cover in my name") {
                    $is_sapa                = true;
                    $sapa['insured']        = $proposal_addtional_details['prepolicy']['cPAInsComp'];
                    $sapa['policy_no']      = $proposal_addtional_details['prepolicy']['cPAPolicyNo'];
                    $sapa['start_date']     = $proposal_addtional_details['prepolicy']['cpaPolicyStartDate'];
                    $sapa['end_date']       = $proposal_addtional_details['prepolicy']['cpaPolicyEndDate'];
                } elseif ($selected_addons->compulsory_personal_accident[0]['reason'] == "I have another PA policy with cover amount of INR 15 Lacs or more") {
                    $is_sapa                = true;
                    $sapa['insured']        = $proposal_addtional_details['prepolicy']['cPAInsComp'];
                    $sapa['policy_no']      = $proposal_addtional_details['prepolicy']['cPAPolicyNo'];
                    $sapa['start_date']     = $proposal_addtional_details['prepolicy']['cpaPolicyStartDate'];
                    $sapa['end_date']       = $proposal_addtional_details['prepolicy']['cpaPolicyEndDate'];
                } elseif ($selected_addons->compulsory_personal_accident[0]['reason'] == "I do not have a valid driving license.") {
                    $driver_declaration    = "ODD02";
                    $is_valid_license      = 'N';
                } else {
                    $driver_declaration    = "ODD01";
                }
            }
        }

        //rto code DL condition
        if (strtoupper($proposal->vehicale_registration_number) == 'NEW') {

            $proposal_addtional_details['vehicle']['regNo1'] = 'NEW';
            $proposal_addtional_details['vehicle']['regNo2'] = '';
            $proposal_addtional_details['vehicle']['regNo3'] = '';
            $RegistrationNo_2 = '';
        } else {
            $reg_no = explode('-', $proposal->vehicale_registration_number);
            $RegistrationNo_2 = $reg_no[1];
            if (isset($reg_no[0]) && ($reg_no[0] == 'DL') && (intval($reg_no[1]) < 10) && strlen($reg_no[1]) >= 2) {
                $RegistrationNo_2 = substr($reg_no[1], 1);
            }

            $RegistrationNo_4       = $proposal_addtional_details['vehicle']['regNo3'];
            $proposal_addtional_details['vehicle']['regNo3'] = ((strlen($RegistrationNo_4) == 1) ? '000' . $RegistrationNo_4 : ((strlen($RegistrationNo_4) == 2) ? '00' . $RegistrationNo_4 : ((strlen($RegistrationNo_4) == 3) ? '0' . $RegistrationNo_4 : $RegistrationNo_4)));
        }
        if (strlen($proposal_addtional_details['vehicle']['regNo2']) == 3 && $RegistrationNo_2 < 10) $RegistrationNo_2 = str_replace('0', '', $RegistrationNo_2);

        $proposal_date = date('d/m/Y');


        if ($requestData->vehicle_owner_type == "I") {
            if (in_array(strtoupper($proposal->gender), ['M', 'MALE'])) {
                $insured_prefix = 'Mr';
            } else {
                if (in_array(strtoupper($proposal->gender), ['F', 'FEMALE']) && $proposal->marital_status == "Single") {
                    $insured_prefix = 'Mrs';
                } else {
                    $insured_prefix = 'Miss';
                }
            }
        } else {
            $insured_prefix = 'M/S';
        }

        $FuelType       = strtoupper($mmv->txt_fuel);
        $cngLpgIDV      = $externalCNGKITSI ?? 0;
        $inbuiltCNG  = -1;
        $inbuiltLPG  = -1;
        if ($FuelType == 'PETROL' || $FuelType == 'DIESEL') {
            $inbuiltCNG = 0;
            $inbuiltLPG = 0;
            if ($cngLpgIDV == 0 || $cngLpgIDV == '') {
                $cngLpgIDV = 0;
            }
        } elseif ($FuelType == 'CNG') {
            $inbuiltLPG     = 0;
        } elseif ($FuelType == 'LPG') {
            $inbuiltCNG = 0;
        }

        $legal_liability_to_paid_driver_flag = (($is_od || $llpaidDriver == "N") ? '0' : '1');

        $insurer = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();
        $insurer = keysToLower($insurer);
        $branch_details = DB::table('united_india_financier_branch_masters')->where('financier_branch_code', $proposal->financer_location)->first();


        $proposal_salutation     = ($is_individual ? (in_array(strtoupper($proposal->gender), ['M', 'Male']) ? 'MR.' : (($proposal->marital_status == 'M') ? 'MRS.' : 'MS.')) : 'M/S');

        if(config('CHECK_DIAMLER_MANUFACTURER') == 'Y' && ($mmv->manufacturer = 'DIAMLER (DICV)' || $mmv->manufacturer = 'DIAMLER INDIA COMMERCIAL VEHICLES P LTD.')){
            $mmv->manufacturer = 'BHARAT BENZ';
        }

        $quote_array = [
            'HEADER' => [

                // POLICY AND BUSINESS TYPE
                'NUM_CLIENT_TYPE'                   => ($is_individual ? 'I' : 'C'),
                'NUM_POLICY_TYPE'                   => ($is_package ? 'PackagePolicy' : 'LiabilityOnly'),
                'NUM_BUSINESS_CODE'                 => $btype_name,

                // POLICY AND REGISSTRATION DATES
                'DAT_PREV_POLICY_EXPIRY_DATE'       => Carbon::parse($proposal->prev_policy_expiry_date)->format('d/m/Y'),
                'DAT_PROPOSAL_DATE'                 => $proposal_date,
                'DAT_DATE_OF_EXPIRY_OF_POLICY'      => Carbon::parse($policy_end_date)->format('d/m/Y'),
                'DAT_DATE_OF_ISSUE_OF_POLICY'       => Carbon::parse($policy_start_date)->format('d/m/Y'),
                'DAT_DATE_OF_REGISTRATION'          => Carbon::parse($requestData->vehicle_register_date)->format('d/m/Y'),
                'DAT_DATE_OF_PURCHASE'              => Carbon::parse($requestData->vehicle_register_date)->format('d/m/Y'),
                // 'DAT_UTR_DATE'                      => '',
                'DAT_DRIVING_LICENSE_EXP_DATE'      => '',

                // personal detals
                'DAT_HOURS_EFFECTIVE_FROM'          => '0:00:00',
                'TXT_TITLE'                         => $proposal_salutation,
                'MEM_ADDRESS_OF_INSURED'            => ($proposal->address_line1 . ' ' . $proposal->address_line2 . ' ' . $proposal->address_line3),
                'TXT_MOBILE'                        => $proposal->mobile_number,
                'TXT_EMAIL_ADDRESS'                 => $proposal->email,
                'TXT_GENDER'                        => ($is_individual ? $proposal->gender : ""),
                'TXT_DOB'                           => ($is_individual ? date('d/m/Y', strtotime($proposal->dob)) : ""),
                'NUM_PIN_CODE'                      => (string)($proposal->pincode),
                'TXT_NAME_OF_INSURED'               => ($proposal->first_name . ' ' . $proposal->last_name),
                'TXT_NAME_OF_NOMINEE'               => ($is_individual ? ($proposal->nominee_name ?? '') : ''),

                'NOCLAIMBONUSDISCOUNT'              => 0,
                'NUM_COMPULSORY_EXCESS_AMOUNT'      => 0,
                'NUM_DAYS_COVER_FOR_COURTESY'       => 0,
                'NUM_GEOGRAPHICAL_EXTN_PREM'        => 0,


                'TXT_PAN_NO'                        => $proposal->pan_number ?? '',
                'TXT_GSTIN_NUMBER'                  => $proposal->gst_number ?? '',

                'TXT_AADHAR_NUMBER'                 => '',
                'TXT_ENROLLMENT_NO'                 => '',
                'DAT_ENROLEMENT_DATE'               => '',

                'TXT_OCCUPATION'                    => $proposal->occupation,

                // RTO
                'TXT_RTA_DESC'                      => $rto_data->txt_rta_code,
                'TXT_VEHICLE_ZONE'                  => $rto_data->txt_registration_zone,

                // MMV
                'NUM_CUBIC_CAPACITY'                => $mmv->cubiccapacity,
                'NUM_RGSTRD_SEATING_CAPACITY'       => $mmv->seatingcapacity,
                'NUM_RGSTRD_GROSS_VEH_WEIGHT'       => $mmv->grossvehicleweight ?? '0',
                'NUM_RGSTRD_CARRYING_CAPACITY'      => $mmv->carryingcapacity ?? '0',
                'TXT_FUEL'                          => strtoupper($mmv->txt_fuel) == 'CNG' ? 'PETROL/CNG' : strtoupper($mmv->txt_fuel),
                'TXT_NAME_OF_MANUFACTURER'          => strtoupper($mmv->manufacturer),
                'TXT_OTHER_MAKE'                    => $mmv->vehiclemodel,
                'TXT_VARIANT'                       => $mmv->txt_variant,
                'NUM_VEHICLE_MODEL_CODE'            => '',
                'TXT_TYPE_BODY'                     => !empty($mmv->body_type) ? $mmv->body_type : $mmv->fyntune_version['vehicle_built_up'] ?? '',//we are not getting body type in master so we passing vehicle_built_up
                'TXT_VAHICLE_COLOR'                 => 'NA',

                // CPA
                'YN_COMPULSORY_PA_DTLS'             => $cpa_cover,
                'YN_VALID_DRIVING_LICENSE'          => $requestData->vehicle_owner_type == "I" ? $is_valid_license : 'N',
                'TXT_CPA_COVER_PERIOD'              => (($cpa_cover) ? $cpa_cover_period : ''),

                'TXT_RELATION_WITH_NOMINEE'         => ($is_individual ? ($proposal->nominee_relationship ?? '') : ''), //$nominee['relation'],
                'NUM_VOLUNTARY_EXCESS_AMOUNT'       => 0, //$voluntary_deductable,
                'NUM_IMPOSED_EXCESS_AMOUNT'         => '', //$voluntary_deductable,

                // NO CPA REASON
                'YN_SCPA_PA_COVER_AVAILABLE'        => ($is_sapa ? 'Y' : 'N'),

                "TXT_SCPA_PA_POL_INSURER_NAME"      => $sapa['insured'],
                "TXT_SCPA_PA_POL_NUMBER"            => $sapa['policy_no'],
                "DAT_SCPA_PA_POL_START_DATE"        => ($is_sapa ? Carbon::parse($sapa['start_date'])->format('d/m/Y') : ''),
                "DAT_SCPA_PA_POL_END_DATE"          => ($is_sapa ? Carbon::parse($sapa['end_date'])->format('d/m/Y') : ''),

                // FINANCIER
                'NUM_AGREEMENT_NAME_1'              => ($proposal->is_vehicle_finance ? $proposal->financer_agreement_type : ''),
                'NUM_FINANCIER_NAME_1'              => ($proposal->is_vehicle_finance ? ($proposal->full_name_finance ?? ($proposal->name_of_financer ?? '')) : ''),
                'TXT_FIN_ACCOUNT_CODE_1'            => ($proposal->is_vehicle_finance ? ($branch_details->financier_code ?? '') : ''),
                'TXT_FIN_BRANCH_NAME_1'             => ($proposal->is_vehicle_finance ? ($branch_details->branch_name ?? '') : ''),
                'TXT_FINANCIER_BRANCH_ADDRESS1'     => ($proposal->is_vehicle_finance ? ($branch_details->branch_address ?? '') : ''),

                'NUM_AGREEMENT_NAME_2'              => '',
                'NUM_FINANCIER_NAME_2'              => '',
                'TXT_FIN_ACCOUNT_CODE_2'            => '',
                'TXT_FIN_BRANCH_NAME_2'             => '',
                'TXT_FINANCIER_BRANCH_ADDRESS2'     => '',

                // ADDONS
                'YN_NIL_DEPR_WITHOUT_EXCESS'        => $zero_dep,
                'YN_CONSUMABLE'                     => $consumable,
                'YN_RTI_APPLICABLE'                 => $return_to_invoice,
                'YN_NCB_PROTECT'                    => $ncb_protection,

                //Y—for Engine and Gear Box applicable
                'YN_ENGINE_GEAR_COVER_PLATINUM'     => $engine_secure,

                'YN_LOSS_OF_KEY'                    => $key_replacement,
                'NUM_LOSS_OF_KEY_SUM_INSURED'       => (($key_replacement == 'Y') ? '10000' : ''),

                'YN_TYRE_RIM_PROTECTOR'             => $tyre_secure,
                'NUM_TYRE_RIM_SUM_INSURED'          => (($tyre_secure == 'Y') ? '50000' : '0'),
                'YN_RSA_COVER'                      => $road_side_assistance, // Y/N RSA ADDON

                // 'YN_EMI_COVER'                      => '', //Y if applicable,N if not applicable
                // 'NUM_EMI_COVER_AMOUNT'              => 0,

                //COVERS
                'NUM_IEV_CNG_VALUE'                 => $cngLpgIDV,
                'YN_INBUILT_CNG'                    => $inbuiltCNG,
                'YN_INBUILT_LPG'                    => $inbuiltLPG,
                'NUM_IEV_LPG_VALUE'                 => '',

                'YN_CLAIM'                          => (($requestData->is_claim == 'N') ? 'no' : 'yes'),
                'YN_PAID_DRIVER'                    => $yn_paid_driver,
                'CUR_BONUS_MALUS_PERCENT'           => ($requestData->is_claim == 'Y') ? '0' : $requestData->previous_ncb,

                'NUM_LL1'                           => ($llpaidDriver == 'Y') ? $LLnumberSum : 0,
                'YN_ANTI_THEFT'                     => $anti_theft_flag,

                // 'NUM_VOLUNTARY_EXCESS_AMOUNT'       => ((isset($requestData->voluntary_excess_value) && $requestData->voluntary_excess_value != 0) ? $requestData->voluntary_excess_value : '0'),
                // 'NUM_IMPOSED_EXCESS_AMOUNT'         => ((isset($requestData->voluntary_excess_value) && $requestData->voluntary_excess_value != 0) ? $requestData->voluntary_excess_value : '0'),

                'NUM_IEV_ELEC_ACC_VALUE'            => (int)(!is_null($requestData->electrical_acessories_value) && $requestData->electrical_acessories_value != '0') ? $requestData->electrical_acessories_value : '0',
                'ELECTRICALACCESSORIESPREM'         => (!is_null($requestData->electrical_acessories_value) && $requestData->electrical_acessories_value != '0') ? $requestData->electrical_acessories_value : '0',

                'TXT_ELEC_DESC'                     => '',

                'NUM_IEV_NON_ELEC_ACC_VALUE'        => (int)(!is_null($requestData->nonelectrical_acessories_value) && $requestData->nonelectrical_acessories_value != '0') ? $requestData->nonelectrical_acessories_value  : '0',
                'NONELECTRICALACCESSORIESPREM'      => (!is_null($requestData->nonelectrical_acessories_value) && $requestData->nonelectrical_acessories_value != '0') ? $requestData->nonelectrical_acessories_value : '0',

                'TXT_NON_ELEC_DESC'                 => '',

                'NUM_PA_UNNAMED_AMOUNT'             => ($requestData->unnamed_person_cover_si != '' && !$is_od) ? $requestData->unnamed_person_cover_si : '0',
                'NUM_PA_UNNAMED_NUMBER'             => ((!$is_od && $requestData->unnamed_person_cover_si != '') ? ($mmv->seatingcapacity - 1) : 0),

                'NUM_TPPD_AMOUNT'                   => (!$is_od ? '750000' : '0'),
                'TXT_BUS_CATEGORY_C2'                  => $bus_category,

                // VEHICLE
                'NUM_YEAR_OF_MANUFACTURE'           => Carbon::parse('01-'.$requestData->manufacture_year)->format('Y'),

                'TXT_REGISTRATION_NUMBER_1'         => $requestData->business_type == "newbusiness" ? 'NEW' : explode('-', $proposal_addtional_details['vehicle']['regNo1'])[0],
                'TXT_REGISTRATION_NUMBER_2'         => $requestData->business_type == "newbusiness" ? '' : $RegistrationNo_2,
                'TXT_REGISTRATION_NUMBER_3'         => $requestData->business_type == "newbusiness" ? '' : $proposal_addtional_details['vehicle']['regNo2'],
                'TXT_REGISTRATION_NUMBER_4'         => $requestData->business_type == "newbusiness" ? '' : $proposal_addtional_details['vehicle']['regNo3'],

                'TXT_ENGINE_NUMBER'                 => $proposal->engine_number,
                'TXT_CHASSIS_NUMBER'                => $proposal->chassis_number,
                'NUM_IEV_BASE_VALUE'                => ((!$is_liability) ? $mmv->idv : '0'),
                'NUM_MONTH_OF_MANUFACTURE'          => Carbon::parse('01-'.$requestData->manufacture_year)->format('m'),
                'NUM_IEV_TRAILER_VALUE'             => 0, //Total Trailer Idv

                // PREVIOUS INSURER
                'TXT_PREVIOUS_INSURER'              => $proposal->previous_insurance_company,
                'TXT_PREV_INSURER_CODE'             => $proposal->previous_insurance_company,
                'NUM_POLICY_NUMBER'                 => $proposal->previous_policy_number,
                'NUM_PREVIOUS_IDV'                  => '',


                //CKYC

                'TXT_CKYC_NO'                       => '', //CKYC number of Customer
                'TXT_CKYC_ADDL_INFO'                => '', //Oem unique Id generated during KYC verification . Mandatory for every proposal

                //'TXT_OEM_DEALER_CODE'               =>  config('constants.IcConstants.united_india.CV.OEM_DEALER_CODE'), //'BRC0000796',
                //'TXT_OEM_MISP_CODE'                 => config('constants.IcConstants.united_india.CV.OEM_MISP_CODE'),

                'TXT_OEM_TRANSACTION_ID'            => 'TEST123',

                'TXT_TRANSACTION_ID'                => '',

                'TXT_MERCHANT_ID'                   => config('constants.IcConstants.united_india.CV.PAYMENT_MERCHANT_ID'),

                'NUM_NO_OF_NAMED_DRIVERS'           => $PAforaddionaldPaidDriver,
                'NUM_SPECIAL_DISCOUNT_RATE'         => 0,
                'NUM_UTR_PAYMENT_AMOUNT'            => '',
                'ODDiscount'                        => 0,
                'PAODPremium'                       => 0,
                // 'TXT_AA_DISC_PREM'                  => '0',
                // 'TXT_AA_FLAG'                       => '',
                'TXT_MEDICLE_COVER_LIMIT'           => '',
                'TXT_TELEPHONE'                     => '',
                'YN_COMMERCIAL_FOR_PRIVATE'         => 0,
                'YN_COURTESY_CAR'                   => 0,
                'YN_DELETION_OF_IMT26'              => $imt_23,
                'YN_DRIVING_TUTION'                 => 0,
                'YN_FOREIGN_EMBASSY'                => 0,
                'YN_HANDICAPPED'                    => 0,
                'YN_IMT32'                          => 0,
                'YN_LIMITED_TO_OWN_PREMISES'        => ((!$is_liability) ? $is_limited_to_own_premises : 0 ),
                'YN_MEDICLE_EXPENSE'                => 0,

                'YN_PERSONAL_EFFECT'                => 0, //Personal Effects(0-No,-1-Yes)
                'CUR_LD_PERSONAL_EFFECT'            => 0, //Personal Effect value(5000/10000)
                'CUR_ADDLTOWCHARGE'                 => 0,
                'YN_PLATINUM_PA'                    => 'N', //Platinum PA for Occupants Add-On Cover       (Y-Yes, N-No)
                'NUM_PLATINUM_PA_SUM_INSURED'       => 0, //Sum Insured for Platinum PA for Occupants Add-On Cover ( PC – 500000/1000000/1500000 )
                'YN_PETCARE'                        => 'N', //Y if applicable,N if not applicable
                'TXT_PETCARE_DESCRIPTION'           => '',
                'NUM_PETCARE_SUM_INSURED'           => 0,
                'TXT_FASTAG_ID'                     => '', //FAST TAG ID- 24 alphanumeric characters


                'NUM_LL2'                           => '',
                'NUM_LL3'                           => ($nfpp) ? $nfpp_si : 0,
                'NUM_PAID_UP_CAPITAL'               => '',
                // 'NUM_PA_NAME1_AMOUNT'               => '',
                // 'NUM_PA_NAME2_AMOUNT'               => '',
                // 'NUM_PA_NAME3_AMOUNT'               => '',
                // 'NUM_PA_NAME4_AMOUNT'               => '',
                // 'NUM_PA_NAME5_AMOUNT'               => '',
                // 'NUM_PA_NAME6_AMOUNT'               => '',
                // 'NUM_PA_NAME7_AMOUNT'               => '',
                // 'NUM_PA_NAME8_AMOUNT'               => '',
                // 'NUM_PA_NAMED_AMOUNT'               => '',
                // 'NUM_PA_NAMED_NUMBER'               => '',
                // 'TXT_AA_MEMBERSHIP_NAME'            => '',
                // 'TXT_AA_MEMBERSHIP_NUMBER'          => '',
                // 'TXT_BANK_CODE'                     => '',
                // 'TXT_BANK_NAME'                     => '',
                'TXT_DRIVING_LICENSE_NO'            => '',
                // 'TXT_PA_NAME1'                      => '',
                // 'TXT_PA_NAME2'                      => '',
                // 'TXT_PA_NAME3'                      => '',
                // 'TXT_PA_NAME4'                      => '',
                // 'TXT_PA_NAME5'                      => '',
                // 'TXT_PA_NAME6'                      => '',
                // 'TXT_PA_NAME7'                      => '',
                // 'TXT_PA_NAME8'                      => '',
                // 'TXT_UTR_NUMBER'                    => '',
                'NUM_IEV_FIBRE_TANK_VALUE'          => '',
                'NUM_IEV_SIDECAR_VALUE'             => '',
                'NUM_LD_CLEANER_CONDUCTOR'          => $PAforaddionaldPaidDriverSI,
                'TXT_GEOG_AREA_EXTN_COUNTRY'        => $geo_extn_countries,
                'TXT_LICENSE_ISSUING_AUTHORITY'     => '',
                // 'TXT_MEMBERSHIP_CODE'               => '',
                // 'TXT_NAMED_PA_NOMINEE1'             => '',
                // 'TXT_NAMED_PA_NOMINEE2'             => '',
                // 'TXT_NAMED_PA_NOMINEE3'             => '',
                // 'TXT_NAMED_PA_NOMINEE4'             => '',
                // 'TXT_NAMED_PA_NOMINEE5'             => '',
                // 'TXT_NAMED_PA_NOMINEE6'             => '',
                // 'TXT_NAMED_PA_NOMINEE7'             => '',
                // 'TXT_NAMED_PA_NOMINEE8'             => '',
                'TXT_CKYC_NO'                       => ($proposal->ckyc_type == 'ckyc_number') ? $proposal->ckyc_type_value : '',
                'TXT_CKYC_ADDL_INFO'                => ($proposal->ckyc_type == 'ckyc_number') ? '' : $proposal->ckyc_reference_id,
                'CUR_ADDLTOWCHARGE'                 => $addtowcharge
            ]
        ];
        
        //$is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $misp_testing_mode = config('MISP_TESTING_MODE_ENABLE_UNITED_INDIA_CV') == 'Y';
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->whereIn('seller_type', ['P','misp'])
            ->first();
        if (isset($pos_data->seller_type) && $pos_data->seller_type == 'P') 
        {
            $quote_array['HEADER']['TXT_POSP_CODE'] = $pos_data->relation_united_india;
            $quote_array['HEADER']['TXT_POSP_NAME'] = $pos_data->agent_name;
            if (empty($pos_data->relation_united_india))
            {
                return [
                    'status' => false,
                    'message' => 'POSP CODE not available',
                ];
            }
        }
        else if(isset($pos_data->seller_type) && $pos_data->seller_type == 'misp')
        {
            $quote_array['HEADER']['TXT_OEM_DEALER_CODE'] = $pos_data->relation_united_india;
            if (empty($pos_data->relation_united_india) && !$misp_testing_mode)
            {
                return [
                    'status' => false,
                    'message' => 'MISP CODE not available',
                ];
            }
            if($misp_testing_mode)
            {
                $quote_array['HEADER']['TXT_OEM_DEALER_CODE'] = config('constants.IcConstants.united_india.CV.OEM_DEALER_CODE'); //'BRC0000796',              
            }
        }

        $rtoCode = explode("-", $requestData->rto_code);

        if (isset($rtoCode[0]) && in_array(strtoupper($rtoCode[0]), ['DL'])) {
            if (isset($rtoCode[1]) && is_numeric($rtoCode[1]) && $rtoCode[1] > 0 && $rtoCode[1] < 10) {
                $quote_array['HEADER']['TXT_RTA_DESC'] = $rto_data->rto_location_desc;
            }
        }

        $quote_array['HEADER']['NUM_VEHICLE_MODEL_CODE'] = $mmv->vehiclemodelcode;

        $request_container = '
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://ws.uiic.com/">
            <soapenv:Header/>
            <soapenv:Body>
                <ws:calculatePremium>
                    <application>' . config('constants.IcConstants.united_india.CV.APPLICATION_ID') . '</application>
                    <userid>' . config('constants.IcConstants.united_india.CV.USER_ID') . '</userid>
                    <password>' . config('constants.IcConstants.united_india.CV.USER_PASSWORD') . '</password>
                    <proposalXml>
                        <![CDATA[#replace]]>
                    </proposalXml>
                    <productCode>' . $product_code . '</productCode>
                    <subproductCode>' . $sub_product_code . '</subproductCode>
                </ws:calculatePremium>
            </soapenv:Body>
            </soapenv:Envelope>
        ';

        if(in_array($mmv->manufacturer,['MARUTI SUZUKI','MARUTI']) && $requestData->applicable_ncb == 0 && !$is_liability )
        {
            if($age>5){
                $age = '5 to 10';
            }
            elseif($age>10){
                $age = 'Above 10';
            }
            $Updated_discount_rto_data = unitedIndiaRtoCityDiscountCv::where('rto_code',$rto_data->txt_rta_code)->select('discount_grid_rto_city')->first();
            $Updated_discount_grid_value = unitedIndiaWithoutNcbDiscountGridCv::where([['age',$age],['rto_city_location',$Updated_discount_rto_data['discount_grid_rto_city']]])
            ->first();
            
            $Updated_discount_grid = array_change_key_case((array)$Updated_discount_grid_value?->toArray(), CASE_LOWER)?? null;
            $mmv_model = str_replace([' ', '-'], '_', $mmv->vehiclemodel);
            $model_data = strtolower($mmv_model.'_'.$mmv->txt_fuel) ?? null;

            $Updated_discount_grid_data = $Updated_discount_grid[$model_data]  ?? null;
            if(!$Updated_discount_grid_data){
            $mmv_model = str_replace('-', '_', $mmv->vehiclemodel);
            $model_data = strtolower($mmv_model) ?? null;

            // $model_data = str_replace('-', ' ', $model_name);
            $Updated_discount_grid_data = $Updated_discount_grid[$model_data]  ?? null;
            }
            if($Updated_discount_grid_data){
                $quote_array['HEADER']['NUM_SPECIAL_DISCOUNT_RATE'] = $Updated_discount_grid_data;

            }
            if(!$Updated_discount_grid_data){
                return  [
                    'status'            => false,
                    'message'           =>  $mmv->vehiclemodel .' Not Available In Discount Grid For Maruti',
                ];
            }
        }
        elseif(in_array($mmv->manufacturer,['MARUTI SUZUKI','MARUTI']) && $requestData->applicable_ncb > 0 && !$is_liability)
        {
         
            if($age>5){
                $age = '5 to 10';
            }
            elseif($age>10){
                $age = 'Above 10';
            }
            $Updated_discount_rto_data = unitedIndiaRtoCityDiscountCv::where('rto_code',$rto_data->txt_rta_code)->select('discount_grid_rto_city')->first();
            $Updated_discount_grid_value = unitedIndiaWithNcbDiscountGridCv::where([['Age',$age],['rto_city_location',$Updated_discount_rto_data['discount_grid_rto_city']]])
            ->first();

            $Updated_discount_grid = array_change_key_case((array)$Updated_discount_grid_value?->toArray(), CASE_LOWER)?? null;
            $mmv_model = str_replace([' ', '-'], '_', $mmv->vehiclemodel);
            $model_data = strtolower($mmv_model.'_'.$mmv->txt_fuel) ?? null;

            $Updated_discount_grid_data = $Updated_discount_grid[$model_data]  ?? null;
            
            if(!$Updated_discount_grid_data){
            $mmv_model = str_replace('-', '_', $mmv->vehiclemodel);
            $model_data = strtolower($mmv_model) ?? null;
                
            // $model_data = str_replace('-', ' ', $model_name);
            $Updated_discount_grid_data = $Updated_discount_grid[$model_data]  ?? null;
            }
            if($Updated_discount_grid_data){
                $quote_array['HEADER']['NUM_SPECIAL_DISCOUNT_RATE'] = $Updated_discount_grid_data;

            }
            if(!$Updated_discount_grid_data){
                return  [
                    'status'            => false,
                    'message'           =>  $mmv->vehiclemodel .' Not Available In Discount Grid For Maruti',
                ];
            }
        }

        if (!in_array($premium_type, ['third_party_breakin', 'third_party'])) {
            $agentDiscount = calculateAgentDiscount($enquiryId, 'united_india', strtolower($parent_id));
            if ($agentDiscount['status'] ?? false) {
                $quote_array['HEADER']['NUM_SPECIAL_DISCOUNT_RATE'] = $agentDiscount['discount'];
            } else {
                if (!empty($agentDiscount['message'] ?? '')) {
                    return [
                        'status' => false,
                        'message' => $agentDiscount['message']
                    ];
                }
            }
        }

        // quick quote service input

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [],
            'requestMethod'     => 'post',
            'requestType'       => 'xml',
            'section'           => $productData->product_sub_type_code,
            'productName'       => $productData->product_name,
            'method'            => 'Premium Calculation',
            'transaction_type'  => 'proposal',
            'root_tag'          => 'ROOT',
            'soap_action'       => 'calculatePremium',
            'container'         => $request_container,
        ];

        $get_response = getWsData(config('constants.IcConstants.united_india.CV.END_POINT_URL_SERVICE'), $quote_array, 'united_india', $additional_data);
        $response = $get_response['response'];
        $xhdf = ($response) ? 'true' : 'false';

        if ($response) {

            $quote_output = html_entity_decode($response);
            $quote_output = XmlToArray::convert($quote_output);

            $header = $quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER'];
            $error_message = $header['TXT_ERR_MSG'];

            if ($error_message != [] && $error_message != '') {
                return [
                    'status'    => false,
                    'msg'       => $error_message,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message'   => json_encode($error_message)
                ];
            }

            $total_od_premium       = 0;
            $total_tp_premium       = 0;
            $od_premium             = 0;
            $tp_premium             = 0;
            $liability              = 0;
            $pa_owner               = 0;
            $pa_unnamed             = 0;
            $lpg_cng_amount         = 0;
            $lpg_cng_tp_amount      = 0;
            $electrical_amount      = 0;
            $non_electrical_amount  = 0;
            $paAddionalPaidDriver   = 0;
            $ncb_discount           = 0;
            $discount_amount        = 0;
            $zero_dep_amount        = '';
            $eng_prot               = '';
            $ncb_prot               = '';
            $rsa                    = '';
            $tyre_secure            = '';
            $key_replacement        = '';
            $return_to_invoice      = '';
            $consumable             = '';
            $bonus_discount         = 0;
            $anti_theft_discount    = 0;
            $automobile_association = 0;

            $base_cover = [
                'od_premium'            =>  0,
                'tp_premium'            =>  0,
                'pa_owner'              =>  0,
                'liability'             =>  0,
                'eng_prot'              =>  0,
                'return_to_invoice'     =>  0,
                'zero_dep_amount'       =>  0,
                'medical_expense'       =>  0,
                'consumable'            =>  0,
                'road_side_assistance'  =>  0,
                'imt23'                =>   0,
                'key_replacement'       =>  0,
                'tyre_secure'           =>  0,
                'ncb_protection'        =>  0,
                'additional_towing'     =>  0,
                'nfpp'                  =>  0
            ];
            $base_cover_codes = [
                'od_premium'        =>  'Basic - OD',
                'tp_premium'        =>  'Basic - TP',
                'pa_owner'          =>  'PA Owner Driver',
                'liability'         =>  'LL to Paid Driver IMT 28',
                'eng_prot'          =>  'Engine and Gearbox Protection Platinum AddOn Cover',
                'return_to_invoice' =>  'Return To Invoice',
                'zero_dep_amount'   =>  'Nil Depreciation Without Excess',
                'medical_expense'   =>  'Medical Expenses',
                'consumable'        =>  'Consumables Cover',
                'road_side_assistance' => 'Road Side Assistance',
                'imt23'            =>  'Cover for lamps, tyres, tubes etc',
                'key_replacement'   =>  'Loss Of Key Cover',
                'tyre_secure'       =>  'Tyre And Rim Protector Cover',
                'ncb_protection'    =>  'NCB Protect',
                'additional_towing' =>  'Additional Towing Charge',
                'nfpp'              =>  'Legal Liability to Non-Fare Paying Passenger(Employee)'
            ];
            $base_cover_match_arr = [
                'name'  => 'PropCoverDetails_CoverGroups',
                'value' => 'PropCoverDetails_Premium',
            ];

            $discount_codes = [
                'bonus_discount'            =>  'Bonus Discount - OD',
                'anti_theft_discount'       =>  'Anti-Theft Device - OD',
                'limited_to_own_premises'   =>  'Limited to Own Premises - OD',
                'automobile_association'    =>  'Automobile Association Discount',
                'voluntary'                 =>  'Voluntary Excess Discount',
            ];
            $match_arr = [
                'name'  => 'PropLoadingDiscount_Description',
                'value' => 'PropLoadingDiscount_CalculatedAmount',
            ];
            $discount = [
                'bonus_discount'            =>  0,
                'anti_theft_discount'       =>  0,
                'limited_to_own_premises'   =>  0,
                'automobile_association'    =>  0,
                'voluntary'                 =>  0,
            ];

            $cng_codes = [
                'lpg_cng_tp_amount'     =>  'CNG Kit-TP',
                'lpg_cng_amount'        =>  'CNG Kit-OD',
            ];
            $cng_match_arr = [
                'name'  => 'PropCoverDetails_CoverGroups',
                'value' => 'PropCoverDetails_Premium',
            ];
            $cng = [
                'lpg_cng_tp_amount'     =>  0,
                'lpg_cng_amount'        =>  0,
            ];

            $worksheet = $quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER']['TXT_PRODUCT_USERDATA']['WorkSheet'];
            // print_pre([$worksheet]);

            // print_pre(['foreach block PropRisks_Co -> Risks', $worksheet['PropRisks_Col']['Risks']]);
            if (isset($worksheet['PropRisks_Col']['Risks'][0])) {
                foreach ($worksheet['PropRisks_Col']['Risks'] as $risk_key => $risk_value) {
                    if (is_array($risk_value) && isset($risk_value['PropRisks_SIComponent'])) {
                        if ($risk_value['PropRisks_SIComponent'] == 'VehicleBaseValue') {
                            $base_cover = UnitedIndiaSubmitProposal::united_india_cover_addon_values($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $base_cover_codes, $base_cover_match_arr, $base_cover);
                        }
                        if ($risk_value['PropRisks_SIComponent'] == 'CNG') {
                            $cng = UnitedIndiaSubmitProposal::united_india_cover_addon_values($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $cng_codes, $cng_match_arr, $cng);
                        }
                        if ($risk_value['PropRisks_SIComponent'] == 'Unnamed Hirer or Driver PA') {
                            $pa_unnamed = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                        }
                        if ($risk_value['PropRisks_SIComponent'] == 'PAForPaidDriveretc') {
                            $paAddionalPaidDriver = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                        }
                        if ($risk_value['PropRisks_SIComponent'] == 'ElectricalAccessories') {
                            $electrical_amount = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                        }
                        if ($risk_value['PropRisks_SIComponent'] == 'NonElectricalAccessories') {
                            $non_electrical_amount = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                        }
                    }
                }
            } else {
                $risk_value = $worksheet['PropRisks_Col']['Risks'];
                if (is_array($risk_value) && isset($risk_value['PropRisks_SIComponent'])) {
                    if ($risk_value['PropRisks_SIComponent'] == 'VehicleBaseValue') {
                        $base_cover = UnitedIndiaSubmitProposal::united_india_cover_addon_values($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $base_cover_codes, $base_cover_match_arr, $base_cover);
                    }
                    if ($risk_value['PropRisks_SIComponent'] == 'CNG') {
                        $cng = UnitedIndiaSubmitProposal::united_india_cover_addon_values($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $cng_codes, $cng_match_arr, $cng);
                    }
                    if ($risk_value['PropRisks_SIComponent'] == 'Unnamed Hirer or Driver PA') {
                        $pa_unnamed = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                    }
                    if ($risk_value['PropRisks_SIComponent'] == 'PAForPaidDriveretc') {
                        $paAddionalPaidDriver = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                    }
                    if ($risk_value['PropRisks_SIComponent'] == 'ElectricalAccessories') {
                        $electrical_amount = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                    }
                    if ($risk_value['PropRisks_SIComponent'] == 'NonElectricalAccessories') {
                        $non_electrical_amount = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                    }
                }
            }
            if (!$is_liability) {
                if (is_array($worksheet['PropLoadingDiscount_Col']) && !empty($worksheet['PropLoadingDiscount_Col'])) {
                    $discount = UnitedIndiaSubmitProposal::united_india_cover_addon_values($worksheet['PropLoadingDiscount_Col']['LoadingDiscount'], $discount_codes, $match_arr, $discount);
                }
            }
            $idv = round($quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER']['NUM_IEV_BASE_VALUE']);

            // proposal
            $quote_array['HEADER']['TXT_TRANSACTION_ID']    = $header['TXT_TRANSACTION_ID'];
            $request_container = '
                <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://ws.uiic.com/">
                    <soapenv:Header/>
                    <soapenv:Body>
                        <ws:saveProposal>
                            <application>' . config('constants.IcConstants.united_india.CV.APPLICATION_ID') . '</application>
                            <userid>' . config('constants.IcConstants.united_india.CV.USER_ID') . '</userid>
                            <password>' . config('constants.IcConstants.united_india.CV.USER_PASSWORD') . '</password>
                            <proposalXml>
                                <![CDATA[#replace]]>
                            </proposalXml>
                            <productCode>' . $product_code . '</productCode>
                            <subproductCode>' . $sub_product_code . '</subproductCode>
                        </ws:saveProposal>
                    </soapenv:Body>
                </soapenv:Envelope>
            ';

            // quick quote service input

            $additional_data = [
                'enquiryId'         => $enquiryId,
                'headers'           => [],
                'requestMethod'     => 'post',
                'requestType'       => 'xml',
                'section'           => $productData->product_sub_type_code,
                'productName'       => $productData->product_name,
                'method'            => 'Proposal Submit',
                'transaction_type'  => 'proposal',
                'root_tag'          => 'ROOT',
                'soap_action'       => 'saveProposal',
                'container'         => $request_container,
            ];
            
            $get_response = getWsData(config('constants.IcConstants.united_india.CV.END_POINT_URL_SERVICE'), $quote_array, 'united_india', $additional_data);
            $proposal_response = $get_response['response'];
            
            if (!$response) {
                return [
                    'status'    => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message'   => 'Insurer Not Reachable'
                ];
            }
            
            $proposal_output = html_entity_decode($proposal_response);
            $proposal_output = XmlToArray::convert($proposal_output);
            $proposal_header = $proposal_output['S:Body']['ns2:saveProposalResponse']['return']['ROOT']['HEADER'];
            $error_message = $proposal_header['TXT_ERR_MSG'];

            if ($error_message != [] && $error_message != '') {
                return [
                    'status'    => false,
                    'msg'       => $error_message,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message'   => json_encode($error_message)
                ];
            }
            $vehicleDetails = [
                'manufacture_name'  => $mmv->manufacturer,
                'model_name'        => $mmv->vehiclemodel,
                'version'           => $mmv->txt_variant,
                'fuel_type'         => $mmv->txt_fuel,
                'seating_capacity'  => $mmv->seatingcapacity,
                'carrying_capacity' => $mmv->carryingcapacity,
                'cubic_capacity'    => $mmv->cubiccapacity,
                'gross_vehicle_weight' => $mmv->grossvehicleweight,
                'vehicle_type'      => !empty($mmv->body_type) ? $mmv->body_type : $mmv->fyntune_version['vehicle_built_up'] ?? ''
            ];
            $proposal->proposal_no = $header['TXT_TRANSACTION_ID'];

            $proposal_addtional_details['united_india']['transaction_id']     = $proposal_header['TXT_TRANSACTION_ID'];
            $proposal_addtional_details['united_india']['customer_id']        = $proposal_header['TXT_CUSTOMER_ID'];
            $proposal_addtional_details['united_india']['reference_number']   = $proposal_header['NUM_REFERENCE_NUMBER'];

            $final_total_discount               = (isset($proposal_header['NUM_TOTAL_DEDUCTION_OF_PREMIUM']) ? round($proposal_header['NUM_TOTAL_DEDUCTION_OF_PREMIUM']) : 0);

            $proposal->proposal_date = Carbon::parse(strtr($proposal_header['DAT_REFERENCE_DATE'], ['/' => '-']))->format('Y-m-d H:i:s');

            $proposal->final_payable_amount     = $proposal_header['CUR_FINAL_TOTAL_PREMIUM'];
            $proposal->policy_start_date        = Carbon::parse($policy_start_date)->format('d-m-Y');
            $proposal->policy_end_date          = Carbon::parse($policy_end_date)->format('d-m-Y');
            $proposal->tp_start_date            = Carbon::parse($tp_start_date)->format('d-m-Y');
            $proposal->tp_end_date              = Carbon::parse($tp_end_date)->format('d-m-Y');
            $proposal->od_premium               = (isset($proposal_header['CUR_NET_OD_PREMIUM']) ? round($proposal_header['CUR_NET_OD_PREMIUM']) : 0);
            $proposal->tp_premium               = (isset($proposal_header['CUR_NET_TP_PREMIUM']) ? round($proposal_header['CUR_NET_TP_PREMIUM']) : 0);
            $proposal->cpa_premium              = (isset($proposal_header['NUM_TOTAL_ADDITION_OF_TP_PREM']) ? round($proposal_header['NUM_TOTAL_ADDITION_OF_TP_PREM']) : 0);
            $proposal->addon_premium            = (isset($proposal_header['NUM_TOTAL_ADDITION_OF_PREMIUM']) ? round($proposal_header['NUM_TOTAL_ADDITION_OF_PREMIUM']) : 0);
            $proposal->ncb_discount             = (isset($proposal_header['NUM_TOTAL_DEDUCTION_OF_PREMIUM']) ? round($proposal_header['NUM_TOTAL_DEDUCTION_OF_PREMIUM']) : 0);
            $proposal->service_tax_amount       = (isset($proposal_header['CUR_FINAL_SERVICE_TAX']) ? round($proposal_header['CUR_FINAL_SERVICE_TAX']) : 0);
            $proposal->total_premium            = (isset($proposal_header['CUR_NET_FINAL_PREMIUM']) ? round($proposal_header['CUR_NET_FINAL_PREMIUM']) : 0);
            $proposal->discount_percent         = ($is_liability ? 0 : $requestData->applicable_ncb);

            $proposal->additional_details       = $proposal_addtional_details;
            $additionalDetailsData = json_decode($proposal->additional_details_data, true);
            $additionalDetailsData = array_merge($additionalDetailsData ?? [], $proposal_addtional_details);

            $proposal->additional_details_data = $additionalDetailsData;
            $proposal->ic_vehicle_details       =  $vehicleDetails;
            $proposal->save();

            $data['user_product_journey_id']    = customDecrypt($request['userProductJourneyId']);
            $data['ic_id']                      = $productData->policy_id;
            $data['stage']                      = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $data['proposal_id']                = $proposal->user_proposal_id;

            UnitedIndiaPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

            updateJourneyStage($data);
            return response()->json([
                'status' => true,
                'msg' => 'Proposal Submited Successfully..!',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'data' => camelCase([
                    'proposal_no' => $proposal->proposal_no,
                    'data' => $proposal,
                    'proposal' => $header,
                    'premium_amount' => $proposal->final_payable_amount
                ]),
                'premium_data' => [
                    'base_cover'                => $base_cover,
                    'cng'                       => $cng,
                    'discount'                  => $discount,
                    'pa_unnamed'                => $pa_unnamed,
                    'electrical_amount'         => $electrical_amount,
                    'non_electrical_amount'     => $non_electrical_amount,
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

    public static function united_india_cover_addon_values($value_arr, $cover_codes, $match_arr, $covers)
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
}
