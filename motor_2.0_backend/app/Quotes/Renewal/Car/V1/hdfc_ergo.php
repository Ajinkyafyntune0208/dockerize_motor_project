<?php

use Carbon\Carbon;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\UserProposal;
use App\Models\MasterRto;
use App\Models\HdfcErgoRtoLocation;
use App\Models\MasterPremiumType;
use App\Models\CvAgentMapping;
use app\Quotes\Renewal\Car\V1\hdfc_ergo;

function getV1RenewalQuote($enquiryId, $requestData, $productData)
{
    //    echo $_SERVER['HTTP_USER_AGENT'];
    //    die;
    include_once app_path() . '/Helpers/CarWebServiceHelper.php';
    $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');
    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv
            ]
        ];
    }
    $mmv_data = (object)array_change_key_case((array)$mmv, CASE_LOWER);
    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle Not Mapped',
            ]
        ];
    } elseif ($mmv_data->ic_version_code == 'DNE') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]
        ];
    }
    $vehicle_in_90_days = 'N';
    
    switch ($requestData->business_type) {

        case 'rollover':
            $business_type = 'Roll Over';
            break;

        case 'newbusiness':
            $business_type = 'New Business';
            break;

        default:
            $business_type = $requestData->business_type;
            break;
    }
    $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();

    $ProductCode = '2311';
    if ($premium_type == "third_party") {
        $ProductCode = '2319';
    }
    $zero_dep = '0';
    if ($productData->zero_dep == '0') {
        $zero_dep = '1';
    }
    $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']);
    $od_only = in_array($premium_type, ['own_damage', 'own_damage_breakin']);

    $prev_policy_end_date = $requestData->previous_policy_expiry_date;
    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($prev_policy_end_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $car_age = floor($age / 12);

    //TP Renewal not allowed.

    if($tp_only || $premium_type == 'breakin'){
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Renewal is not allowed incase of Thirdparty or Breakin.'
        ];
    }   

    if ($interval->y >= 15 && $productData->zero_dep == 0) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Zero depreciation is not available for vehicles having age more than 15 years'
        ];
    }
    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
    if (($interval->y >= 15) && ($tp_check == 'true')) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 15 years',
        ];
    }

    $policy_type = '';
    if ($requestData->policy_type == 'comprehensive') {
        $policy_type = 'Comprehensive';
    } elseif ($requestData->policy_type == 'own_damage') {
        $policy_type = 'ODOnly';
    }

    $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
    $policy_no = $user_proposal['previous_policy_number'];

    $transactionid = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 10);

    $SOURCE        = config('IC.HDFC_ERGO.V1.CAR.RENEWAL.SOURCE');
    $CHANNEL_ID    = config('IC.HDFC_ERGO.V1.CAR.RENEWAL.CHANNELID');
    $CREDENTIAL    = config('IC.HDFC_ERGO.V1.CAR.RENEWAL.CREDENTIAL');

    $data_token = [
        'enquiryId' => $enquiryId,
        'productData' => $productData,
        'requestData' => $requestData,
        'business_type' => $business_type,
        'transactionid' => $transactionid,
        'SOURCE'        => $SOURCE,
        'CHANNEL_ID'    => $CHANNEL_ID,
        'CREDENTIAL'    => $CREDENTIAL,
        'ProductCode'   => $ProductCode,
    ];

    $token = getGeneratedToken($data_token);
    if (empty($token)) {
        return [
            'status' => false,
            'premium_amount' => 0,
            'message' => 'Token service not reachable'
        ];
    } else {
        $data_fetch = [
            'enquiryId' => $enquiryId,
            'token'     => $token,
            'policy_no' => $policy_no,
            'productData' => $productData,
            'business_type' => $business_type,
            'transactionid' => $transactionid,
            'SOURCE'        => $SOURCE,
            'CHANNEL_ID'    => $CHANNEL_ID,
            'CREDENTIAL'    => $CREDENTIAL,
            'ProductCode'   => $ProductCode,
            'premium_type'  => $premium_type,
        ];
        $fetch_data = getRenewalFetch($data_fetch);
        if (empty($fetch_data) || !empty($fetch_data->Error)) {
            return [
                'status' => false,
                'premium_amount' => 0,
                'message' => $fetch_data->Error ?? 'Renewal service not reachable'
            ];
        } else {
            $previous_ped = $fetch_data->Resp_RE->PreviousPolicy_PolicyEndDate;
            if (strpos($previous_ped, ':') === false) {
                $previous_ped .= " 12:00:00 AM";
            } else {
                $previous_ped = strpos($previous_ped, 'AM') === false && strpos($previous_ped, 'PM') === false
                ? $previous_ped . " AM"
                    : $previous_ped;
            }
            $policy_start_date = date('d-m-Y', strtotime(str_replace('/', '-', $previous_ped) . ' +1 day'));//Carbon::createFromFormat('d/m/Y', $fetch_data->Resp_RE->PreviousPolicy_PolicyEndDate)->addDay(1)->format('d/m/Y');
            $policy_end_date = date('d-m-Y', strtotime(str_replace('/','-',$policy_start_date) . ' +1 year -1 day'));//Carbon::createFromFormat('d/m/Y', $policy_start_date)->addYear(1)->subDay(1)->format('d/m/Y');
            $model_code = $fetch_data->Resp_RE->VehicleModelCode;
            $rto_code = $fetch_data->Resp_RE->RTOLocationCode;
            $vechile_registration_date = $fetch_data->Resp_RE->DateofDeliveryOrRegistration;
            $registration_number = $fetch_data->Resp_RE->Registration_No;
            $previous_policy_type = $fetch_data->Resp_RE->PreviousPolicy_PolicyType;
            $previous_policy_start_date = $fetch_data->Resp_RE->PreviousPolicy_PolicyStartDate;
            $previous_policy_end_date = $fetch_data->Resp_RE->PreviousPolicy_PolicyEndDate;
            $pp_tp_end_date = $fetch_data->Resp_RE->PreviousPolicy_TPEndDate;
            $pp_tp_start_date = $fetch_data->Resp_RE->PreviousPolicy_TPStartDate;
            $ownerdrivernomineename = $fetch_data->Resp_RE->Owner_Driver_Nominee_Name;
            $ownerdrivernomineeage = $fetch_data->Resp_RE->Owner_Driver_Nominee_Age;
            $cpa_tenure_renewal = $fetch_data->Resp_RE->IsPAOwnerDriver_Cover;
            $zero_dep_YN = $fetch_data->Resp_RE->IsZD_Cover;
            $LL_YN = $fetch_data->Resp_RE->IsPaidDriver_Cover;
            $EA_YN = $fetch_data->Resp_RE->IsEA_Cover;
            $COC_YN = $fetch_data->Resp_RE->IsCOC_Cover;
            $EAW_YN = $fetch_data->Resp_RE->IsEAW_Cover;
            $ENG_YN = $fetch_data->Resp_RE->IsEngGearBox_Cover;
            $NCB_YN = $fetch_data->Resp_RE->IsNCB_Cover;
            $RTOFetch = $fetch_data->Resp_RE->RTOLocationCode;
            if(!empty($RTOFetch)){
                DB::table('user_proposal')
                    ->where('user_product_journey_id', $enquiryId)
                    ->update([
                        'unique_quote' => $RTOFetch,
                    ]);
            }
            $idv_data = $data_fetch;
            $idv_data['registration_number'] = $registration_number;
            $idv_data['model_code'] = $model_code;
            $idv_data['rto_code'] = $rto_code;
            $idv_data['vechile_registration_date'] = $vechile_registration_date;
            $idv_data['policy_start_date'] = $policy_start_date;
            $idv_data['previous_policy_type'] = $previous_policy_type;
            $idv_data['previous_policy_start_date'] = $previous_policy_start_date;
            $idv_data['previous_policy_end_date'] = $previous_policy_end_date;
            $idv_data['pp_tp_end_date'] = $pp_tp_end_date;
            $idv_data['pp_tp_start_date'] = $pp_tp_start_date;
            $idv_data['ownerdrivernomineename'] = $ownerdrivernomineename;
            $idv_data['ownerdrivernomineeage'] = $ownerdrivernomineeage;
            $idv_data['cpa_tenure_renewal'] = $cpa_tenure_renewal;
            $idv_data['zero_dep_YN'] = $zero_dep_YN;
            $idv_data['LL_YN'] = $LL_YN;
            $idv_data['EA_YN'] = $EA_YN;
            $idv_data['COC_YN'] = $COC_YN;
            $idv_data['EAW_YN'] = $EAW_YN;
            $idv_data['ENG_YN'] = $ENG_YN;
            $idv_data['NCB_YN'] = $NCB_YN;
            $idv_calc = getCalcIDV($idv_data);
            if(!empty($idv_calc->Error)){
                return [
                    'status' => false,
                    'premium_amount' => 0,
                    'message' => $idv_calc->Error ?? 'IDV service not reachable'
                ];
            }
            if (in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                $veh_idv = 0;
            } else {
                $veh_idv = $idv_calc->CalculatedIDV->IDV_AMOUNT;
            }
            $idv_min = $idv_calc->CalculatedIDV->MIN_IDV_AMOUNT ?? 0;
            $idv_max = $idv_calc->CalculatedIDV->MAX_IDV_AMOUNT ?? 0;
            $premium_data = $idv_data;

            $premium_data['policy_type'] = $policy_type;
            $premium_data['requestData'] = $requestData;
            $premium_data['enquiryId'] = $enquiryId;
            $premium_data['productData'] = $productData;
            $premium_data['mmv_data'] = $mmv_data;
            $premium_data['veh_idv'] = $veh_idv;
            $data_premium_calc = getCalcPremium($premium_data);
            $data_premium_webservice = $data_premium_calc['webservice_id'];
            $data_premium_table = $data_premium_calc['table'];
            $data_premium_calc = $data_premium_calc['data'];
            $ErrorPrem = $data_premium_calc->Error;
            if (empty($data_premium_calc) || !empty($ErrorPrem)) {
                return [
                    'status' => false,
                    'premium_amount' => 0,
                    'message' => $data_premium_calc->Error ?? 'Premium Calculation service not reachable'
                ];
            } else {
                $premium_data_resp = $data_premium_calc->Resp_PvtCar;
                $idv = ($premium_type != 'third_party') ? (string)round($premium_data_resp->IDV) : '0';
                $igst = $anti_theft = $other_discount = $rsapremium = $pa_paid_driver = $zero_dep_amount
                    = $ncb_discount = $tppd = $final_tp_premium = $final_od_premium =
                    $final_net_premium = $final_payable_amount = $basic_od =
                    $electrical_accessories = $lpg_cng_tp = $lpg_cng =
                    $non_electrical_accessories = $pa_owner = $voluntary_excess =
                    $pa_unnamed = $key_rplc = $tppd_discount = $ll_paid_driver =
                    $personal_belonging = $engine_protection = $consumables_cover =
                    $rti = $tyre_secure = $ncb_protection = $GeogExtension_od = $GeogExtension_tp = $OwnPremises_OD = $OwnPremises_TP = 0;
                $GeogExtension_od = 0;
                $GeogExtension_tp = 0;
                $legal_liability_to_employee = 0;

                if (!empty($premium_data_resp->PAOwnerDriver_Premium)) {
                    $pa_owner = round($premium_data_resp->PAOwnerDriver_Premium);
                }
                if (!empty($premium_data_resp->GeogExtension_ODPremium)) {
                    $GeogExtension_od = round($premium_data_resp->GeogExtension_ODPremium);
                }
                if (!empty($premium_data_resp->GeogExtension_TPPremium)) {
                    $GeogExtension_tp = round($premium_data_resp->GeogExtension_TPPremium);
                }

                if (!empty($premium_data_resp->LimitedtoOwnPremises_OD_Premium)) {
                    $OwnPremises_OD = round($premium_data_resp->LimitedtoOwnPremises_OD_Premium);
                }
                if (!empty($premium_data_resp->LimitedtoOwnPremises_TP_Premium)) {
                    $OwnPremises_TP = round($premium_data_resp->LimitedtoOwnPremises_TP_Premium);
                }
                if (!empty($premium_data_resp->Vehicle_Base_ZD_Premium)) {
                    $zero_dep_amount = round($premium_data_resp->Vehicle_Base_ZD_Premium);
                }
                if (!empty($premium_data_resp->EA_premium)) {
                    $rsapremium = round($premium_data_resp->EA_premium);
                }
                if (!empty($premium_data_resp->Loss_of_Use_Premium)) {
                    $personal_belonging = round($premium_data_resp->Loss_of_Use_Premium);
                }
                if (!empty($premium_data_resp->Vehicle_Base_NCB_Premium)) {
                    $ncb_protection = round($premium_data_resp->Vehicle_Base_NCB_Premium);
                }
                if (!empty($premium_data_resp->NCBBonusDisc_Premium)) {
                    $ncb_discount = round($premium_data_resp->NCBBonusDisc_Premium);
                }
                if (!empty($premium_data_resp->Vehicle_Base_ENG_Premium)) {
                    $engine_protection = round($premium_data_resp->Vehicle_Base_ENG_Premium);
                }
                if (!empty($premium_data_resp->Vehicle_Base_COC_Premium)) {
                    $consumables_cover = round($premium_data_resp->Vehicle_Base_COC_Premium);
                }
                if (!empty($premium_data_resp->Vehicle_Base_RTI_Premium)) {
                    $rti = round($premium_data_resp->Vehicle_Base_RTI_Premium);
                }
                if (!empty($premium_data_resp->EAW_premium)) {
                    $key_rplc = round($premium_data_resp->EAW_premium);
                }
                if (!empty($premium_data_resp->UnnamedPerson_premium)) {
                    $pa_unnamed = round($premium_data_resp->UnnamedPerson_premium);
                }
                if (!empty($premium_data_resp->Electical_Acc_Premium)) {
                    $electrical_accessories = round($premium_data_resp->Electical_Acc_Premium);
                }
                if (!empty($premium_data_resp->NonElectical_Acc_Premium)) {
                    $non_electrical_accessories = round($premium_data_resp->NonElectical_Acc_Premium);
                }
                if (!empty($premium_data_resp->BiFuel_Kit_OD_Premium)) {
                    $lpg_cng = round($premium_data_resp->BiFuel_Kit_OD_Premium);
                }
                if (!empty($premium_data_resp->BiFuel_Kit_TP_Premium)) {
                    $lpg_cng_tp = round($premium_data_resp->BiFuel_Kit_TP_Premium);
                }
                if (!empty($premium_data_resp->PAPaidDriver_Premium)) {
                    $pa_paid_driver = round($premium_data_resp->PAPaidDriver_Premium);
                }
                if (!empty($premium_data_resp->PaidDriver_Premium)) {
                    $ll_paid_driver = round($premium_data_resp->PaidDriver_Premium);
                }
                if (!empty($premium_data_resp->VoluntartDisc_premium)) {
                    $voluntary_excess = round($premium_data_resp->VoluntartDisc_premium);
                }
                if (!empty($premium_data_resp->Vehicle_Base_TySec_Premium)) {
                    $tyre_secure = round($premium_data_resp->Vehicle_Base_TySec_Premium);
                }
                if (!empty($premium_data_resp->AntiTheftDisc_Premium)) {
                    $anti_theft = round($premium_data_resp->AntiTheftDisc_Premium);
                }
                if (!empty($premium_data_resp->Net_Premium)) {
                    $final_net_premium = round($premium_data_resp->Net_Premium);
                }
                if (!empty($premium_data_resp->Total_Premium)) {
                    $final_payable_amount = round($premium_data_resp->Total_Premium);
                }
                if (!empty($premium_data_resp->InBuilt_BiFuel_Kit_Premium)) {
                    $lpg_cng_tp = round($premium_data_resp->InBuilt_BiFuel_Kit_Premium);
                }
                if (!empty($premium_data_resp->NumberOfEmployees_Premium)) {
                    $legal_liability_to_employee = round($premium_data_resp->NumberOfEmployees_Premium);
                }

                $final_tp_premium = round($premium_data_resp->Basic_TP_Premium) + $pa_unnamed + $lpg_cng_tp + $pa_paid_driver + $ll_paid_driver + $GeogExtension_tp + $OwnPremises_TP + $legal_liability_to_employee;
                $final_total_discount = $anti_theft + $ncb_discount + $voluntary_excess + $premium_data_resp->TPPD_premium;

                if ($electrical_accessories > 0) {
                    $zero_dep_amount += (int)$premium_data_resp->Elec_ZD_Premium;
                    $engine_protection += (int)$premium_data_resp->Elec_ENG_Premium;
                    $ncb_protection += (int)$premium_data_resp->Elec_NCB_Premium;
                    $consumables_cover += (int)$premium_data_resp->Elec_COC_Premium;
                    $rti += (int)$premium_data_resp->Elec_RTI_Premium;
                }

                if ($non_electrical_accessories > 0) {
                    $zero_dep_amount += (int)$premium_data_resp->NonElec_ZD_Premium;
                    $engine_protection += (int)$premium_data_resp->NonElec_ENG_Premium;
                    $ncb_protection += (int)$premium_data_resp->NonElec_NCB_Premium;
                    $consumables_cover += (int)$premium_data_resp->NonElec_COC_Premium;
                    $rti += (int)$premium_data_resp->NonElec_RTI_Premium;
                }

                if ($lpg_cng > 0) {
                    $zero_dep_amount += (int)$premium_data_resp->Bifuel_ZD_Premium;
                    $engine_protection += (int)$premium_data_resp->Bifuel_ENG_Premium;
                    $ncb_protection += (int)$premium_data_resp->Bifuel_NCB_Premium;
                    $consumables_cover += (int)$premium_data_resp->Bifuel_COC_Premium;
                    $rti += (int)$premium_data_resp->Bifuel_RTI_Premium;
                }

                $final_od_premium = $premium_data_resp->Basic_OD_Premium + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $GeogExtension_od + $OwnPremises_OD;

                $add_ons = [];
                $applicable_addons = [];
                if ($car_age <= 3) {
                    array_push($applicable_addons, "returnToInvoice");
                }
                if ($car_age == 0 && (int)$personal_belonging > 0) {
                    array_push($applicable_addons, "lopb");
                }
                if ($zero_dep == '1') {
                    $add_ons = [
                        'in_built' => [
                            'zeroDepreciation' => round($zero_dep_amount),
                        ],
                        'additional' => [
                            'roadSideAssistance' => round($rsapremium),
                            'engineProtector' => round($engine_protection),
                            'ncbProtection' => round($ncb_protection),
                            'keyReplace' => round($key_rplc),
                            'consumables' => round($consumables_cover),
                            'tyreSecure' => round($tyre_secure),
                            'returnToInvoice' => round($rti),
                            'lopb' => round($personal_belonging),
                        ],
                        'other' => []
                    ];
                    array_push($applicable_addons, "roadSideAssistance", "ncbProtection", "tyreSecure", "zeroDepreciation", "engineProtector", "consumables", "keyReplace");
                } else {
                    $add_ons = [
                        'in_built' => [],
                        'additional' => [
                            'zero_depreciation' => 0,
                            'roadSideAssistance' => round($rsapremium),
                            'engineProtector' => round($engine_protection),
                            'ncbProtection' => round($ncb_protection),
                            'keyReplace' => round($key_rplc),
                            'consumables' => round($consumables_cover),
                            'tyreSecure' => round($tyre_secure),
                            'returnToInvoice' => round($rti),
                            'lopb' => round($personal_belonging),
                        ],
                        'other' => []
                    ];
                    array_push($applicable_addons, "roadSideAssistance", "ncbProtection", "tyreSecure", "zeroDepreciation", "engineProtector", "consumables", "keyReplace");
                }
                foreach ($add_ons['additional'] as $k => $v) {
                    if (empty($v)) {
                        unset($add_ons['additional'][$k]);
                    }
                }
                if ((int)$tyre_secure == 0 && ($key = array_search('tyreSecure', $applicable_addons)) !== false) {
                    array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                }
                if ((int)$tyre_secure == 0 && ($key = array_search('tyreSecure', $applicable_addons)) !== false) {
                    array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                }
                if ((int)$rsapremium == 0 && ($key = array_search('roadSideAssistance', $applicable_addons)) !== false) {
                    array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                }
                if ((int)$ncb_protection == 0 && ($key = array_search('ncbProtection', $applicable_addons)) !== false) {
                    array_splice($applicable_addons, array_search('ncbProtection', $applicable_addons), 1);
                }
                if ((int)$key_rplc == 0 && ($key = array_search('keyReplace', $applicable_addons)) !== false) {
                    array_splice($applicable_addons, array_search('keyReplace', $applicable_addons), 1);
                }
                if ((int)$consumables_cover == 0 && ($key = array_search('consumables', $applicable_addons)) !== false) {
                    array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                }
                if ((int)$rti == 0 && ($key = array_search('returnToInvoice', $applicable_addons)) !== false) {
                    array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                }
                if ((int)$engine_protection == 0 && ($key = array_search('engineProtector', $applicable_addons)) !== false) {
                    array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                }
                $add_ons['in_built_premium'] = array_sum($add_ons['in_built']);
                $add_ons['additional_premium'] = array_sum($add_ons['additional']);
                $add_ons['other_premium'] = array_sum($add_ons['other']);
                $final_payable_amount = $final_od_premium + $final_tp_premium - $final_total_discount + $add_ons['additional_premium'];
                $final_payable_amount = $final_payable_amount * (1 + (18.0 / 100));
                $data_response = [
                    'webservice_id' => $data_premium_webservice,
                    'table' => $data_premium_table,
                    'status' => true,
                    'msg' => 'Found',
                    'Data' => [
                        'isRenewal' => 'Y',
                        'idv' => (int)$idv,
                        'min_idv' => (int)$idv_min,
                        'max_idv' => (int)$idv_max,
                        'vehicle_idv' => $idv,
                        'qdata' => null,
                        'pp_enddate' => $requestData->previous_policy_expiry_date,
                        'addonCover' => null,
                        'addon_cover_data_get' => '',
                        'rto_decline' => null,
                        'rto_decline_number' => null,
                        'mmv_decline' => null,
                        'mmv_decline_name' => null,
                        'policy_type' => ($premium_type == 'third_party' ? 'Third Party' : ($premium_type == 'own_damage' ? 'Own Damage' : 'Comprehensive')),
                        'business_type' => $business_type,
                        'cover_type' => '1YC',
                        'hypothecation' => '',
                        'hypothecation_name' => '',
                        'vehicle_registration_no' => $requestData->rto_code,
                        'rto_no' => $rto_code,
                        'version_id' => $requestData->version_id,
                        'selected_addon' => [],
                        'showroom_price' => 0,
                        'fuel_type' => $requestData->fuel_type,
                        'ncb_discount' => $requestData->applicable_ncb,
                        'company_name' => $productData->company_name,
                        'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
                        'product_name' => $productData->product_sub_type_name,
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
                            'logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                            'product_sub_type_name' => $productData->product_sub_type_name,
                            'flat_discount' => $productData->default_discount,
                            'predefine_series' => '',
                            'is_premium_online' => $productData->is_premium_online,
                            'is_proposal_online' => $productData->is_proposal_online,
                            'is_payment_online' => $productData->is_payment_online,
                        ],
                        'motor_manf_date' => $requestData->vehicle_register_date,
                        'vehicle_register_date' => $requestData->vehicle_register_date,
                        'vehicleDiscountValues' => [
                            'master_policy_id' => $productData->policy_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'segment_id' => 0,
                            'rto_cluster_id' => 0,
                            'car_age' => $car_age,
                            'aai_discount' => 0,
                            'ic_vehicle_discount' => 0,
                        ],
                        'ic_vehicle_discount' => 0,
                        'basic_premium' => ($premium_type != 'third_party') ? (string)round($premium_data_resp->Basic_OD_Premium) : '0',
                        'motor_electric_accessories_value' => round($electrical_accessories),
                        'motor_non_electric_accessories_value' => round($non_electrical_accessories),
                        'motor_lpg_cng_kit_value' => round($lpg_cng),
                        // 'GeogExtension_ODPremium' => round($GeogExtension_od),
                        // 'GeogExtension_TPPremium' => round($GeogExtension_tp),
                        // 'LimitedtoOwnPremises_TP'=>round($OwnPremises_TP),
                        // 'LimitedtoOwnPremises_OD'=>round($OwnPremises_OD),
                        'total_accessories_amount(net_od_premium)' => round($electrical_accessories + $non_electrical_accessories + $lpg_cng),
                        'total_own_damage' => round($final_od_premium),
                        'tppd_premium_amount' => (string)round($premium_data_resp->Basic_TP_Premium),
                        'tppd_discount' => round($premium_data_resp->TPPD_premium),
                        'compulsory_pa_own_driver' => round($pa_owner), // Not added in Total TP Premium
                        'cover_unnamed_passenger_value' => (int)$pa_unnamed,
                        'default_paid_driver' => (int)$ll_paid_driver,
                        'motor_additional_paid_driver' => round($pa_paid_driver),
                        'GeogExtension_ODPremium'                     => $GeogExtension_od,
                        'GeogExtension_TPPremium'                     => $GeogExtension_tp,
                        'cng_lpg_tp' => round($lpg_cng_tp),
                        'seating_capacity' => $mmv_data->seating_capacity,
                        'deduction_of_ncb' => round(abs($ncb_discount)),
                        'antitheft_discount' => round(abs($anti_theft)),
                        'aai_discount' => '', //$automobile_association,
                        'voluntary_excess' => $voluntary_excess,
                        'other_discount' => 0,
                        'total_liability_premium' => round($final_tp_premium),
                        'net_premium' => round($final_payable_amount),
                        'service_tax_amount' => round($final_payable_amount - $final_net_premium),
                        'service_tax' => 18,
                        'total_discount_od' => 0,
                        'add_on_premium_total' => 0,
                        'addon_premium' => 0,
                        'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                        'quotation_no' => '',
                        'premium_amount' => round($final_payable_amount),
                        'service_data_responseerr_msg' => 'success',
                        'cpa_allowed' => !$od_only && $cpa_tenure_renewal > 0 ? true : false,
                        'user_id' => $requestData->user_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'user_product_journey_id' => $requestData->user_product_journey_id,
                        'service_err_code' => null,
                        'service_err_msg' => null,
                        'policyStartDate' => $policy_start_date,
                        'policyEndDate' => $policy_end_date,
                        'ic_of' => $productData->company_id,
                        'vehicle_in_90_days' => $vehicle_in_90_days,
                        'get_policy_expiry_date' => null,
                        'get_changed_discount_quoteid' => 0,
                        'vehicle_discount_detail' => [
                            'discount_id' => null,
                            'discount_rate' => null,
                        ],
                        'other_covers' => [
                            'LegalLiabilityToEmployee' => $legal_liability_to_employee ?? 0
                        ],
                        'premium_data' => $premium_data_resp,
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online,
                        'policy_id' => $productData->policy_id,
                        'insurane_company_id' => $productData->company_id,
                        'max_addons_selection' => null,
                        'add_ons_data' => $add_ons,
                        'applicable_addons' => $applicable_addons,
                        'final_od_premium' => round($final_od_premium),
                        'final_tp_premium' => round($final_tp_premium),
                        'final_total_discount' => round(abs($final_total_discount)),
                        'final_net_premium' => round($final_payable_amount),
                        // 'final_gst_amount' => round($final_payable_amount - $final_net_premium),
                        'final_payable_amount' => round($final_payable_amount),
                        'mmv_detail' => [
                            'manf_name' => $mmv_data->vehicle_manufacturer,
                            'model_name' => $mmv_data->vehicle_model_name,
                            'version_name' => $mmv_data->variant,
                            'fuel_type' => $mmv_data->fuel,
                            'seating_capacity' => $mmv_data->seating_capacity,
                            'carrying_capacity' => $mmv_data->carrying_capacity,
                            'cubic_capacity' => $mmv_data->cubic_capacity,
                            'gross_vehicle_weight' => '',
                            'vehicle_type' => 'Private Car',
                        ],
                    ]
                ];
                if (!empty($legal_liability_to_employee)) {

                    $data_response['Data']['other_covers'] = [
                        'LegalLiabilityToEmployee' => $legal_liability_to_employee ?? 0
                    ];
                }
                if ($data_response['Data']['cng_lpg_tp'] == 0) {
                    unset($data_response['Data']['cng_lpg_tp']);
                }
                if ($data_response['Data']['motor_lpg_cng_kit_value'] == 0) {
                    unset($data_response['Data']['motor_lpg_cng_kit_value']);
                }

                $return_data = camelCase($data_response);
                return $return_data;
            }
        }
    }
}

function getGeneratedToken($data_token)
{
    extract($data_token);

    $PRODUCT_CODE  = $ProductCode;
    $TRANSACTIONID = $transactionid;

    $additionData = [
        'type'              => 'gettoken',
        'method'            => 'tokenGeneration',
        'section'           => 'car',
        'productName'       => $productData->product_name . " ($business_type)",
        'enquiryId'         => $enquiryId,
        'transaction_type'  => 'quote',
        'PRODUCT_CODE'      => $PRODUCT_CODE,
        'SOURCE'            => $SOURCE,
        'CHANNEL_ID'        => $CHANNEL_ID,
        'TRANSACTIONID'     => $TRANSACTIONID,
        'CREDENTIAL'        => $CREDENTIAL,
        // 'headers'            => [
        //     'PRODUCT_CODE'      => $PRODUCT_CODE,
        //     'SOURCE'            => $SOURCE,
        //     'CHANNEL_ID'        => $CHANNEL_ID,
        //     'TRANSACTIONID'     => $TRANSACTIONID,
        //     'CREDENTIAL'        => $CREDENTIAL,
        // ],
    ];
    $token_gen = getWsData(config('IC.HDFC_ERGO.V1.CAR.RENEWAL.TOKEN_GENERATION_URL'), '', 'hdfc_ergo', $additionData);
    $token_data = json_decode($token_gen['response'], TRUE);
    $auth_token = !empty($token_data['Authentication']['Token']) ? $token_data['Authentication']['Token'] : false;
    return $auth_token;
}

function getRenewalFetch($fetch_data)
{
    extract($fetch_data);
    $PRODUCT_CODE  = $ProductCode;
    $TRANSACTIONID = $transactionid;
    $additionData = [
        'type'              => 'getrenewal',
        'method'            => 'Renewal Fetch Policy',
        'section'           => 'car',
        'requestMethod'     => 'post',
        'productName'       => $productData->product_name . " Renewal",
        'enquiryId'         => $enquiryId,
        'transaction_type'  => 'quote',
        'SOURCE'            => $SOURCE,
        'CHANNEL_ID'        => $CHANNEL_ID,
        'PRODUCT_CODE'      => $PRODUCT_CODE,
        'CREDENTIAL'        => $CREDENTIAL,
        'TRANSACTIONID'     => $TRANSACTIONID,
        'TOKEN'             => $token,
        // 'headers'           => [
        //     'SOURCE'            => $SOURCE,
        //     'CHANNEL_ID'        => $CHANNEL_ID,
        //     'PRODUCT_CODE'      => $PRODUCT_CODE,
        //     'CREDENTIAL'        => $CREDENTIAL,
        //     'TRANSACTIONID'     => $TRANSACTIONID,
        //     'TOKEN'             => $token,
        //     'Content-Type'      => 'application/json',
        // ],
    ];
    $renewal_fetch_data_array = [
        'TransactionID'     => $TRANSACTIONID,
        'Req_Renewal' => [
            "Policy_no" => $policy_no,
        ]
    ];
    $renewal_fetch_data = getWsData(config('IC.HDFC_ERGO.V1.CAR.RENEWAL.FETCH_GENERATION_URL'), $renewal_fetch_data_array, 'hdfc_ergo', $additionData);
    if (!$renewal_fetch_data['response']) {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'Renewal Service Issue',
        ];
    }
    // dd(json_decode($renewal_fetch_data['response']));
    return json_decode($renewal_fetch_data['response']);
}

function getCalcIDV($idv_data)
{
    extract($idv_data);
    $PRODUCT_CODE  = $ProductCode;
    $TRANSACTIONID = $transactionid;
    $additionData = [
        'type'              => 'IDVCalculation',
        'method'            => 'IDVCalculation',
        'section'           => 'car',
        'requestMethod'     => 'post',
        'productName'       => $productData->product_name . " Renewal",
        'enquiryId'         => $enquiryId,
        'SOURCE'            => $SOURCE,
        'CHANNEL_ID'        => $CHANNEL_ID,
        'PRODUCT_CODE'      => $PRODUCT_CODE,
        'CREDENTIAL'        => $CREDENTIAL,
        'TRANSACTIONID'     => $TRANSACTIONID,
        'TOKEN'             => $token,
        'transaction_type'  => 'quote',
    ];
    $idv_request_array = [
        'TransactionID' => $TRANSACTIONID,
        'IDV_DETAILS' => [
            'Policy_Start_Date' => $policy_start_date,
            'ModelCode' => $model_code,
            'RTOCode' => $rto_code,
            'Registration_No' => $registration_number,
            'Vehicle_Registration_Date' => $vechile_registration_date,
            'PreviousPolicyType' => strtoupper($previous_policy_type),
            'PreviousPolicy_PolicyEndDate' => $previous_policy_end_date,
            'PreviousPolicy_TPENDDATE' => $pp_tp_end_date ?? null,
            'PreviousPolicy_TPSTARTDATE' => $pp_tp_start_date ?? null,
        ]
    ];

    if (!in_array($premium_type, ['third_party', 'third_party_breakin'])) {
        $getidvdata = getWsData(config('IC.HDFC_ERGO.V1.CAR.RENEWAL.IDV_CALCULATION_URL'), $idv_request_array, 'hdfc_ergo', $additionData);
        if (!$getidvdata['response']) {
            return [
                'webservice_id' => $getidvdata['webservice_id'],
                'table' => $getidvdata['table'],
                'status' => false,
                'premium' => 0,
                'message' => 'Idv Service Issue',
            ];
        }
        return json_decode($getidvdata['response']);
    }
}

function getCalcPremium($premium_data)
{
    extract($premium_data);

    $prev_policy_end_date = $requestData->previous_policy_expiry_date;
    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($prev_policy_end_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $car_age = floor($age / 12);

    $without_addon = ($productData->product_identifier == 'without_addon') ? true : false;

    $is_loss_of_use_opted = ($car_age == 0) ? 1 : 0;

    if (config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_NO_LOSS_OF_BELONGINGS') == 'Y') {
        $is_loss_of_use_opted = 0;
    }

    //POS.
    $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
    $pos_data = CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
                            ->where('seller_type', 'P')
                            ->first();
                            
    $is_cng = in_array($mmv_data->fuel, ['LPG', 'CNG']);
    $electrical_accessories_sa = 0;
    $non_electrical_accessories_sa = 0;
    $lp_cng_kit_sa = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
    $is_lpg_cng_kit = 'NO';
    $motor_cnglpg_type = '';
    $pa_paid_driver = 'NO';
    $pa_paid_driver_sa = 0;
    $is_pa_unnamed_passenger = 'NO';
    $pa_unnamed_passenger_sa = 0;
    $pa_unnamed_passenger = 0;
    $is_ll_paid_driver = 'NO';
    $is_tppd_discount = 'NO';
    $geoExtension = $LLtoPaidDriverYN = '0';

    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)
        ->first();
    $zero_dep = '0';
    if ($productData->zero_dep == '0') {
        $zero_dep = '1';
    }

    $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
    $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
    if ($selected_addons) {
        foreach ($accessories as $key => $value) {
            if (in_array('geoExtension', $value)) {
                $geoExtension = '1';
            }
            if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                $externalCNGKIT = 'LPG';
                $externalCNGKITSI = $value['sumInsured'];
            }
        }
        foreach ($additional_covers as $key => $value) {
            if (in_array('LL paid driver', $value) || isset($LL_YN)) {
                $LLtoPaidDriverYN = '1';
            }
            if (in_array('PA cover for additional paid driver', $value)) {
                $PAPaidDriverConductorCleaner = 1;
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }
        }
        if ($selected_addons['accessories'] != NULL && $selected_addons['accessories'] != '') {
            foreach ($selected_addons['accessories'] as $accessory) {
                if ($accessory['name'] == 'Electrical Accessories') {
                    $electrical_accessories_sa = $accessory['sumInsured'];
                } elseif ($accessory['name'] == 'Non-Electrical Accessories') {
                    $non_electrical_accessories_sa = $accessory['sumInsured'];
                } elseif ($accessory['name'] == 'External Bi-Fuel Kit CNG/LPG' && !$is_cng) {
                    $is_lpg_cng_kit = 'YES';
                    $lp_cng_kit_sa = $accessory['sumInsured'];
                    $motor_cnglpg_type = 'CNG';
                }
            }
        }

        if ($selected_addons['additional_covers'] != NULL && $selected_addons['additional_covers'] != '') {
            foreach ($selected_addons['additional_covers'] as $additional_cover) {
                if ($additional_cover['name'] == 'PA cover for additional paid driver') {
                    $pa_paid_driver = 'YES';
                    $pa_paid_driver_sa = $additional_cover['sumInsured'];
                }

                if ($additional_cover['name'] == 'Unnamed Passenger PA Cover') {
                    $is_pa_unnamed_passenger = 'YES';
                    $pa_unnamed_passenger = $mmv_data->seating_capacity;
                    $pa_unnamed_passenger_sa = $additional_cover['sumInsured'];
                }

                if ($additional_cover['name'] == 'LL paid driver') {
                    $is_ll_paid_driver = 'YES';
                }
            }
        }

        if ($selected_addons['discounts'] != NULL && $selected_addons['discounts'] != '') {
            foreach ($selected_addons['discounts'] as $discount) {
                if ($discount['name'] == 'TPPD Cover') {
                    $is_tppd_discount = $tp_only ? 'YES' : 'NO';
                }
            }
        }
    }
    $PRODUCT_CODE  = $ProductCode;
    $TRANSACTIONID = $transactionid;
    $additionData = [
        'type'              => 'PremiumCalculation',
        'method'            => 'PremiumCalculation',
        'section'           => 'car',
        'requestMethod'     => 'post',
        'productName'       => $productData->product_name . " Renewal",
        'enquiryId'         => $enquiryId,
        'transaction_type'  => 'quote',
        'SOURCE'            => $SOURCE,
        'CHANNEL_ID'        => $CHANNEL_ID,
        'PRODUCT_CODE'      => $PRODUCT_CODE,
        'CREDENTIAL'        => $CREDENTIAL,
        'TRANSACTIONID'     => $TRANSACTIONID,
        'TOKEN'             => $token,
        
    ];
    // dd($LL_YN);
    $premium_request_array = [
        'TransactionID' => $TRANSACTIONID,
        'GoGreen' => 'false',
        'IsReadyToWait' => null,
        'PolicyCertificateNo' => null,
        'PolicyNo' => $policy_no,
        'Customer_Details' => null,
        'Policy_Details' => [
            'Policy_Start_Date' => $policy_start_date,
            'ProposalDate' => $policy_start_date,
            'BusinessType_Mandatary' => $business_type,
            'VehicleModelCode' => $model_code,
            'DateofDeliveryOrRegistration' => $vechile_registration_date,
            'DateofFirstRegistration' => $vechile_registration_date,
            'YearOfManufacture' => Carbon::createFromFormat('d/m/Y',$vechile_registration_date)->format('Y'),
            'RTOLocationCode' => $rto_code,
            'Vehicle_IDV' => $veh_idv,
            'PreviousPolicy_NCBPercentage' => $requestData->previous_ncb,
            'PreviousPolicy_PolicyEndDate' => $previous_policy_end_date,
            'PreviousPolicy_PolicyClaim' => ($requestData->is_claim == 'N') ? 'NO' : 'YES',
            'PreviousPolicy_PolicyNo' => $policy_no,
            "EngineNumber" => "TREYTREGREGER",
            "FinancierCode" => "",
            "ChassisNumber" => "TREYTREGREGERRRRR",
            "AgreementType" => "",
            "BranchName" => "",
            "PreviousPolicy_IsZeroDept_Cover" => $without_addon ? false : true,
            "PreviousPolicy_IsRTI_Cover" => $without_addon ? false : true,
            "PreviousPolicy_CorporateCustomerId_Mandatary" => 'HDFCERGO',
            "PreviousPolicy_PolicyStartDate" => $previous_policy_start_date,
            "PreviousPolicy_PreviousPolicyType" => $previous_policy_type,
            "PreviousPolicy_TPENDDATE" => $pp_tp_end_date,
            "PreviousPolicy_TPSTARTDATE" => $pp_tp_start_date,
            "PreviousPolicy_TPINSURER" => null,
            "PreviousPolicy_TPPOLICYNO" => null,
            "PolicyEndDate" => null,
            "EndorsementEffectiveDate" => null,
            "SumInsured" => 0,
            "Premium" => 0,
            "EXEMPTED_KERALA_FLOOD_CESS" => null,
            "CUSTOMER_STATE_CD" => 0,
        ],
        "Req_GCV" => null,
        "Req_MISD" => null,
        "Req_PCV" => null,
        "Payment_Details" => null,
        'Req_PvtCar' => [
            'IsLimitedtoOwnPremises' => '0',
            'ExtensionCountryCode' => $geoExtension,
            "ExtensionCountryName" => '',
            'BiFuelType' => ($externalCNGKITSI > 0 ? "CNG" : ""),
            'BiFuel_Kit_Value' => $externalCNGKITSI,
            'POLICY_TYPE' => (($premium_type == 'own_damage') ? 'OD Only' : (($premium_type == "third_party") ? '' : 'OD Plus TP')), // as per the IC in case of tp only value for POLICY_TYPE will be null
            'LLPaiddriver' => $LL_YN ? $LL_YN : $LLtoPaidDriverYN,
            'PAPaiddriverSI' => $PAPaidDriverConductorCleanerSI,
            'IsZeroDept_Cover' => $without_addon ? 0 : $zero_dep_YN,
            'IsNCBProtection_Cover' => $without_addon ? 0 : $NCB_YN,//($car_age <= 5 && !$without_addon) ? 1 : 0,
            'IsRTI_Cover' => 0,//($interval->days <= 1093 && !$without_addon) ? 1 : 0,
            'IsCOC_Cover' => $without_addon ? 0 : $COC_YN,
            'IsEngGearBox_Cover' => $without_addon ? 0 : $ENG_YN,
            'IsEA_Cover' => $without_addon ? 0 : $EA_YN,
            'IsEAW_Cover' => $without_addon ? 0 : $EAW_YN,
            'IsTyreSecure_Cover' => (!in_array($mmv_data->vehicle_manufacturer, ['HONDA', 'TATA MOTORS LTD']) && ($car_age <= 3) && !$without_addon) ? 1 : 0,
            'NoofUnnamedPerson' => $pa_unnamed_passenger,
            'IsLossofUseDownTimeProt_Cover' => $without_addon ? 0 : $is_loss_of_use_opted,
            'UnnamedPersonSI' => $pa_unnamed_passenger_sa,
            'ElecticalAccessoryIDV' => $electrical_accessories_sa,
            'NonElecticalAccessoryIDV' => $non_electrical_accessories_sa,
            'CPA_Tenure' => $cpa_tenure_renewal,//(($premium_type == 'own_damage') ? '0' : '1'), // By Default CPA will be 1 Year
            'Effectivedrivinglicense' => (($premium_type == 'own_damage') ? 'true' : 'false'),
            'Voluntary_Excess_Discount' => 0, //$voluntary_deductible, // Voluntary Deductible discount is removed.
            'POLICY_TENURE' => (($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') ? '3' : '1'),
            // 'TPPDLimit' => $tppd_cover, as per #23856
            "Owner_Driver_Nominee_Age" => $ownerdrivernomineeage ?? '',
            "Owner_Driver_Nominee_Name" => $ownerdrivernomineename ?? '',
            "Owner_Driver_Nominee_Relationship" => "1311",
            'NumberOfEmployees' => ($requestData->vehicle_owner_type == 'C' ? $mmv_data->seating_capacity  : 0),
            'POSP_CODE' => '',
            'fuel_type'   => $mmv_data->fuel,
        ],
        "IDV_DETAILS" => null,
        "Req_ExtendedWarranty" => null,
        "Req_Policy_Document" => null,
        "Req_PEE" => null,
        "Req_TW" => null,
        "Req_RE" => null,
        "Req_Fire2111" => null,
        "Req_ClaimIntimation" => null,
        "Req_ClaimStatus" => null,
        "Req_HInsurance" => null,
        "Req_IPA" => null,
        "Req_CI" => null,
        "Req_HomeInsurance" => null,
        "Req_RetailTravel" => null,
        "Req_HCA" => null,
        "Req_HF" => null,
        "Req_HI" => null,
        "Req_HSTPI" => null,
        "Req_HSTPF" => null,
        "Req_ST" => null,
        "Req_WC" => null,
        "Req_BSC" => null,
        "Req_Discount" => null,
        "Req_POSP" => null,
        "Req_HSF" => null,
        "Req_HSI" => null,
        "Req_CustDec" => null,
        "Req_TW_Multiyear" => null,
        "Req_OptimaRestore" => null,
        "Req_Aviation" => null,
        'Req_Renewal'    => [
            'Policy_No'  => $policy_no,
            'Vehicle_Regn_No' => $registration_number,
            'IDV' => $veh_idv
        ],
    ];
    if($car_age > 5 && $requestData->applicable_ncb != 0 && $zero_dep_YN == 1.0){
        $premium_request_array['Req_PvtCar']['planType'] = 'Essential ZD plan';
    }
    if(config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' && $veh_idv >= 5000000){
        $premium_request_array['Req_POSP'] = null;
    }
    else if($is_pos_enabled == 'Y' && !empty($pos_data)){
        $premium_request_array['Req_POSP'] = [
            'EMAILID' => $pos_data->agent_email,
            'NAME' => $pos_data->agent_name,
            'UNIQUE_CODE' => $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : '',
            'STATE' => '',
            'PAN_CARD' => $pos_data->pan_no,
            'ADHAAR_CARD' => $pos_data->aadhar_no,
            'NUM_MOBILE_NO' => $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : ''
        ];
    }
    if(!empty($cpa_tenure_renewal)){
        $premium_request_array['Req_PvtCar']['CPA_Tenure'] = '1';
    }
    else{
        $premium_request_array['Req_PvtCar']['CPA_Tenure'] = '0';
    }
    $getpremiumresponse = getWsData(config('IC.HDFC_ERGO.V1.CAR.RENEWAL.PREMIUM_CALCULATION_URL'), $premium_request_array, 'hdfc_ergo', $additionData);
    if (!$getpremiumresponse['response']) {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'Premium Calculation Service Issue',
        ];
    }
    return [
        'data' => json_decode($getpremiumresponse['response']),
        'webservice_id' => $getpremiumresponse['webservice_id'], 'table' => $getpremiumresponse['table']
    ];
}
