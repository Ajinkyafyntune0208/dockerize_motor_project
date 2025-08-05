<?php

namespace App\Http\Controllers\Proposal\Services\Renewal\Car\V1;

use App\Http\Controllers\SyncPremiumDetail\Car\HdfcErgoPremiumDetailController;
use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\CvAgentMapping;
use App\Models\SelectedAddons;
use App\Models\CvBreakinStatus;
use App\Models\MasterPremiumType;
use Illuminate\Support\Facades\DB;
use App\Models\AgentIcRelationship;
use App\Models\HdfcErgoRtoLocation;
use App\Models\HdfcErgoPinCityState;
use App\Models\HdfcErgoMotorPincodeMaster;
use App\Models\HdfcErgoV1MotorPincodeMaster;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\ProposalExtraFields;
use Illuminate\Support\Facades\Storage;

use function PHPSTORM_META\map;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

class hdfcErgoSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function renewalSubmit($proposal, $request)
    {
        $requestData = getQuotation($proposal->user_product_journey_id);
        $enquiryId   = customDecrypt($request['enquiryId']);
        $productData = getProductDataByIc($request['policyId']);
        $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');
        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];
        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();
        $master_policy = MasterPolicy::find($request['policyId']);
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $quote_data = json_decode($quote_log->quote_data, true);


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
        $policy_type = '';
        if ($requestData->policy_type == 'comprehensive') {
            $policy_type = 'Comprehensive';
        } elseif ($requestData->policy_type == 'own_damage') {
            $policy_type = 'ODOnly';
        }
        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();
        $ProductCode = '2311';
        if ($premium_type == "third_party") {
            $ProductCode = '2319';
        }

        $cPAInsComp = $cPAPolicyNo = $cPASumInsured = $cPAPolicyFmDt = $cPAPolicyToDt = $cpareason = '';
        $PreviousPolicy_IsZeroDept_Cover = $PreviousPolicy_IsRTI_Cover = false;

        if(!empty($proposal->previous_policy_addons_list))
        {
            $previous_policy_addons_list = is_array($proposal->previous_policy_addons_list) ? $proposal->previous_policy_addons_list : json_decode($proposal->previous_policy_addons_list);
            foreach ($previous_policy_addons_list as $key => $value) {
               if($key == 'zeroDepreciation' && $value)
               {
                    $PreviousPolicy_IsZeroDept_Cover = true;  
               }
               else if($key == 'returnToInvoice' && $value)
               {
                    $PreviousPolicy_IsRTI_Cover = true;
               }
            }                
        }
        
        $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']);
        $od_only = in_array($premium_type, ['own_damage', 'own_damage_breakin']);

        $prev_policy_end_date = $requestData->previous_policy_expiry_date;
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($prev_policy_end_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $car_age = floor($age / 12);

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

        $token = self::getGeneratedToken($data_token);

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
            $fetch_data = self::getRenewalFetch($data_fetch);
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
                $idv_calc = self::getCalcIDV($idv_data);
                if(!empty($idv_calc->Error)){
                    return [
                        'status' => false,
                        'premium_amount' => 0,
                        'message' => $idv_calc->Error ?? 'IDV service not reachable'
                    ];
                }
            }
            if (in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                $veh_idv = 0;
            } else {
                $veh_idv = $idv_calc->CalculatedIDV->IDV_AMOUNT;
            }
            $idv_min = $idv_calc->CalculatedIDV->MIN_IDV_AMOUNT ?? 0;
            $idv_max = $idv_calc->CalculatedIDV->MAX_IDV_AMOUNT ?? 0;
            $premium_data = $idv_data;
            $premium_data['PreviousPolicy_IsZeroDept_Cover'] = $PreviousPolicy_IsZeroDept_Cover;
            $premium_data['PreviousPolicy_IsRTI_Cover'] = $PreviousPolicy_IsRTI_Cover;
            $premium_data['policy_type'] = $policy_type;
            $premium_data['requestData'] = $requestData;
            $premium_data['enquiryId'] = $enquiryId;
            $premium_data['productData'] = $productData;
            $premium_data['mmv_data'] = $mmv_data;
            $premium_data['veh_idv'] = $veh_idv;
            $premium_data['proposal'] = $proposal;
            $data_premium_calc = self::getCalcPremium($premium_data);
            $data_premium_webservice = $data_premium_calc['webservice_id'];
            $data_premium_table = $data_premium_calc['table'];
            $extension_data = $data_premium_calc['extension_data'];
            $data_premium_calc = $data_premium_calc['data'];
            $ErrorPrem = $data_premium_calc->Error;
            if (empty($data_premium_calc) || !empty($ErrorPrem)) {
                return [
                    'status' => false,
                    'premium_amount' => 0,
                    'message' => $data_premium_calc->Error ?? 'Premium Calculation service not reachable'
                ];
            } else {
                $vehicleDetails = [
                    'manufacture_name' => $mmv_data->vehicle_manufacturer,
                    'model_name' => $mmv_data->vehicle_model_name,
                    'version' => $mmv_data->variant,
                    'fuel_type' => $mmv_data->fuel,
                    'seating_capacity' => $mmv_data->seating_capacity,
                    'carrying_capacity' => $mmv_data->carrying_capacity,
                    'cubic_capacity' => $mmv_data->cubic_capacity,
                    'gross_vehicle_weight' => $mmv_data->veh_gvw ?? 1,
                    'vehicle_type' => $mmv_data->veh_ob_type ?? '',
                ];
                $proposal->ic_vehicle_details = $vehicleDetails;
                $proposal_data = $premium_data;
                $proposal_data['quote'] = $quote;
                $proposal_data['quote_log'] = $quote_log;
                $proposal_data['quote_data'] = $quote_data;
                // $proposal_data['proposal'] = $proposal;
                $proposal_data['extension_data'] = $extension_data;
                $premium_data_resp = $data_premium_calc->Resp_PvtCar;
                $idv = ($premium_type != 'third_party') ? (string)round($premium_data_resp->IDV) : '0';
                $igst = $anti_theft = $other_discount = $rsapremium = $pa_paid_driver = $zero_dep_amount
                    = $ncb_discount = $tppd = $tp_premium = $od_premium =
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

                HdfcErgoPremiumDetailController::saveV1PremiumDetails($data_premium_webservice);
                $addon_premium = $zero_dep_amount + $tyre_secure + $consumables_cover + $ncb_protection + $rsapremium + $key_rplc + $personal_belonging + $engine_protection + $rti;
                $tp_premium = (round($premium_data_resp->Basic_TP_Premium) + $pa_owner + $ll_paid_driver + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp)-$premium_data_resp->TPPD_premium+$GeogExtension_tp+ $OwnPremises_TP + $legal_liability_to_employee;
                $od_premium = $premium_data_resp->Basic_OD_Premium + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $GeogExtension_od + $OwnPremises_OD;
                $proposal_sub = self::getCalcProposal($proposal_data);
                $pra = $proposal_sub['proposal_request_array'];
                $ErrorPro = $proposal_sub['errortags'];
                $proposal_sub = $proposal_sub['data'];

                // dd($proposal_sub->Error);
                if(empty($proposal_sub) || !empty($ErrorPro)){
                    return [
                        'status' => false,
                        'premium_amount' => 0,
                        'message' => $proposal_sub->Error ?? 'Proposal Calculation service not reachable' 
                    ];
                }
                else{
                    $additionData = [
                        'type'              => 'ProposalCalculation',
                        'method'            => 'GET CIS Document',
                        'section'           => 'car',
                        'requestMethod'     => 'post',
                        'productName'       => $productData->product_name . " ($business_type)",
                        'enquiryId'         => $enquiryId,
                        'transaction_type'  => 'proposal',
                        'PRODUCT_CODE'      => $ProductCode,
                        'SOURCE'            => $SOURCE,
                        'CHANNEL_ID'        => $CHANNEL_ID,
                        'TRANSACTIONID'     => $transactionid,
                        'CREDENTIAL'        => $CREDENTIAL,
                        'TOKEN'             => $token
                    ];
                    if(!empty($proposal_sub->Policy_Details->ProposalNumber)){
                        $get_cis_document_array = [
                            'TransactionID' => $transactionid,
                            'Req_Policy_Document' => [
                                'Proposal_Number' => $proposal_sub->Policy_Details->ProposalNumber ?? null,
                            ],
                        ];

                        $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_CREATE_CIS_DOCUMENT'), $get_cis_document_array, 'hdfc_ergo', $additionData);
                        $cis_doc_resp = json_decode($get_response['response']);
                        $pdfData = base64_decode($cis_doc_resp->Resp_Policy_Document->PDF_BYTES);
                        if (checkValidPDFData($pdfData)) {
                            Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($proposal->user_proposal_id) .'_cis' .'.pdf', $pdfData);

                            // $pdf_url = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/'. md5($proposal->user_proposal_id) .'_cis' . '.pdf';
                            ProposalExtraFields::insert([
                                'enquiry_id' => $enquiryId,
                                'cis_url'    => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/'. md5($proposal->user_proposal_id) .'_cis' . '.pdf'
                            ]);
                        }else{
                            return response()->json([
                                'status' => false,
                                'webservice_id' => $data_premium_webservice,
                                'table' => $data_premium_table,
                                'msg'    => $cis_doc_resp->Error ?? 'CIS Document service Issue'
                            ]);
                        }
                    }
                    UserProposal::where('user_product_journey_id', $enquiryId)
                        ->where('user_proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'policy_start_date' => (str_replace('/', '-', $policy_start_date)),
                            'policy_end_date' =>  (str_replace('/', '-', $policy_end_date)),
                            'tp_start_date' => (str_replace('/', '-', $policy_start_date)),
                            'tp_end_date' => (str_replace('/', '-', $policy_end_date)),
                            'proposal_no' => $proposal_sub->Policy_Details->ProposalNumber,
                            'unique_proposal_id' => $proposal_sub->Policy_Details->ProposalNumber,
                            'product_code'      => $ProductCode,
                            'od_premium' => $od_premium,
                            'business_type' => $business_type,
                            'tp_premium' => $tp_premium,
                            'addon_premium' => $addon_premium,
                            'cpa_premium' => $pa_owner,
                            'applicable_ncb' => $requestData->applicable_ncb,
                            'final_premium' => round($final_net_premium),
                            'total_premium' => round($final_net_premium),
                            'service_tax_amount' => round($premium_data_resp->Service_Tax),
                            'final_payable_amount' => round($final_payable_amount),
                            'customer_id' => '',
                            'ic_vehicle_details' => json_encode($vehicleDetails),
                            'ncb_discount' => $ncb_discount,
                            'total_discount' => ($ncb_discount + $premium_data_resp->Basic_OD_Premium + $electrical_accessories + $non_electrical_accessories + $lpg_cng + $premium_data_resp->TPPD_premium),
                            'cpa_ins_comp' => $cPAInsComp,
                            'cpa_policy_fm_dt' => str_replace('/', '-', $cPAPolicyFmDt),
                            'cpa_policy_no' => $cPAPolicyNo,
                            'cpa_policy_to_dt' => str_replace('/', '-', $cPAPolicyToDt),
                            'cpa_sum_insured' => $cPASumInsured,
                            'electrical_accessories' => $extension_data['electrical_accessories_sa'],
                            'non_electrical_accessories' => $extension_data['non_electrical_accessories_sa'],
                            'additional_details_data' => json_encode($pra),
                            'is_breakin_case' => 'N',
                        ]);

                    $data['user_product_journey_id'] = $enquiryId;
                    $data['ic_id'] = $master_policy->insurance_company_id;
                    $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                    $data['proposal_id'] = $proposal->user_proposal_id;
                    updateJourneyStage($data);

                    return response()->json([
                        'status' => true,
                        'msg' => $data_premium_calc->Error,
                        'webservice_id' => $data_premium_webservice,
                        'table' => $data_premium_table,
                        'data' => [
                            'proposalId' => $proposal->user_proposal_id,
                            'userProductJourneyId' => $data['user_product_journey_id'],
                            'proposalNo' => $proposal_sub->Policy_Details->ProposalNumber,
                            'finalPayableAmount' => $final_payable_amount,
                            'isBreakinCase' => 'N',
                            'is_breakin'    => 'N',
                            'inspection_number' =>'',
                        ]
                    ]);
                }

            }
        }
    }
    static function getGeneratedToken($data_token)
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
            'transaction_type'  => 'proposal',
            'PRODUCT_CODE'      => $PRODUCT_CODE,
            'SOURCE'            => $SOURCE,
            'CHANNEL_ID'        => $CHANNEL_ID,
            'TRANSACTIONID'     => $TRANSACTIONID,
            'CREDENTIAL'        => $CREDENTIAL,
        ];

        $token_gen = getWsData(config('IC.HDFC_ERGO.V1.CAR.RENEWAL.TOKEN_GENERATION_URL'), '', 'hdfc_ergo', $additionData);
        $token_data = json_decode($token_gen['response'], TRUE);
        $auth_token = !empty($token_data['Authentication']['Token']) ? $token_data['Authentication']['Token'] : false;
        return $auth_token;
    }

    static function getRenewalFetch($fetch_data)
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
            'transaction_type'  => 'proposal',
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

    static function getCalcIDV($idv_data)
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
            'transaction_type'  => 'proposal',
            'SOURCE'            => $SOURCE,
            'CHANNEL_ID'        => $CHANNEL_ID,
            'PRODUCT_CODE'      => $PRODUCT_CODE,
            'CREDENTIAL'        => $CREDENTIAL,
            'TRANSACTIONID'     => $TRANSACTIONID,
            'TOKEN'             => $token,
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

    static function getCalcPremium($premium_data)
    {
        extract($premium_data);

        $prev_policy_end_date = $requestData->previous_policy_expiry_date;
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($prev_policy_end_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $car_age = floor($age / 12);

        //POS
        $pos_data = CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
             ->where('user_proposal_id', $proposal->user_proposal_id)
             ->where('seller_type', 'P')
             ->first();
        
        $is_pos = false;
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $pos_code = '';
        $hdfc_pos_code = '';
        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $veh_idv <= 5000000) {
            if (config('HDFC_CAR_V1_IS_NON_POS') != 'Y') {
                $hdfc_pos_code = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
                    ->pluck('hdfc_ergo_code')
                    ->first();
                if ((empty($hdfc_pos_code) || is_null($hdfc_pos_code))) {
                    return [
                        'status' => false,
                        'premium_amount' => 0,
                        'message' => 'HDFC POS Code Not Available'
                    ];
                }
                $is_pos = true;
                $pos_code = $hdfc_pos_code;
            }
        }
        
        $without_addon = ($productData->product_identifier == 'without_addon') ? true : false;

        $is_loss_of_use_opted = ($car_age == 0) ? 1 : 0;

        if (config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_NO_LOSS_OF_BELONGINGS') == 'Y') {
            $is_loss_of_use_opted = 0;
        }

        $is_cng = in_array($mmv_data->fuel, ['LPG', 'CNG']);
        $electrical_accessories_sa = 0;
        $non_electrical_accessories_sa = 0;
        $lp_cng_kit_sa = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
        $is_lpg_cng_kit = 'NO';
        $motor_cnglpg_type = '';
        $pa_paid_driver = 'NO';
        $pa_paid_driver_sa = 0;
        $is_pa_unnamed_passenger = 'NO';
        $pa_unnamed_passenger_sa = $nilDepreciationCover = $ncb_protction = $RSACover = $KeyReplacementYN = $InvReturnYN = $engine_protection = $tyresecure = $LossOfPersonBelongYN = $consumable = 0;
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
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        // dd($selected_addons);
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
                if (in_array('LL paid driver', $value)) {
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
        if ($requestData->vehicle_owner_type == "I") {
            if (strtoupper($proposal->gender) == "MALE" || strtoupper($proposal->gender) == "M") {
                $salutation = 'MR';
            } else {
                if ((strtoupper($proposal->gender) == "FEMALE" || strtoupper($proposal->gender) == "F") && $proposal->marital_status == "Single") {
                    $salutation = 'MS';
                } else {
                    $salutation = 'MRS';
                }
            }
        } else {
            $salutation = 'M/S';
        }
        foreach ($addons as $key => $value) {
            if (in_array('Zero Depreciation', $value)) {
                $nilDepreciationCover = '1';
            }
            if (in_array('Road Side Assistance', $value)) {
                $RSACover = '1';
            }
            if (in_array('Key Replacement', $value)) {
                $KeyReplacementYN = '1';
            }
            if (in_array('Return To Invoice', $value)) {
                $InvReturnYN = '1';
            }
            if (in_array('NCB Protection', $value)) {
                $ncb_protction = '1';
            }
            if (in_array('Engine Protector', $value)) {
                $engine_protection = '1';
            }
            if (in_array('Consumable', $value)) {
                $consumable = '1';
            }
            if (in_array('Loss of Personal Belongings', $value)) {
                $LossOfPersonBelongYN = '1';
            }
            if (in_array('Tyre Secure', $value) && $car_age <= 3 && !in_array($mmv_data->vehicle_manufacturer, ['HONDA', 'TATA MOTORS LTD'])) {
                $tyresecure = '1';
            }
        }
        $PRODUCT_CODE  = $ProductCode;
        $TRANSACTIONID = $transactionid;
        $additionData = [
            'type'              => 'PremiumCalculation',
            'method'            => 'Premium Calculation',
            'section'           => 'car',
            'requestMethod'     => 'post',
            'productName'       => $productData->product_name . " Renewal",
            'enquiryId'         => $enquiryId,
            'transaction_type'  => 'proposal',
            'SOURCE'            => $SOURCE,
            'CHANNEL_ID'        => $CHANNEL_ID,
            'PRODUCT_CODE'      => $PRODUCT_CODE,
            'CREDENTIAL'        => $CREDENTIAL,
            'TRANSACTIONID'     => $TRANSACTIONID,
            'TOKEN'             => $token,
        ];
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
                'YearOfManufacture' => Carbon::createFromFormat('d/m/Y', $vechile_registration_date)->format('Y'),
                'RTOLocationCode' => $rto_code,
                'Vehicle_IDV' => $veh_idv,
                'PreviousPolicy_NCBPercentage' => $requestData->previous_ncb,
                'PreviousPolicy_PolicyEndDate' => $previous_policy_end_date,
                'PreviousPolicy_PolicyClaim' => ($requestData->is_claim == 'N') ? 'NO' : 'YES',
                'PreviousPolicy_PolicyNo' => $policy_no,
                "EngineNumber" => "TREYTREGREGER",
                "ChassisNumber" => "TREYTREGREGERRRRR",
                'AgreementType' => ($proposal->is_vehicle_finance == '1') ? $proposal->financer_agreement_type : '',
                'FinancierCode' => ($proposal->is_vehicle_finance == '1') ? $proposal->name_of_financer : '',
                'BranchName' => ($proposal->is_vehicle_finance == '1') ? $proposal->hypothecation_city : '',
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
                'IsZeroDept_Cover' => $without_addon ? 0 : $nilDepreciationCover,
                'IsNCBProtection_Cover' => $without_addon ? 0 : (int)$ncb_protction,
                'IsRTI_Cover' => $without_addon ? 0 : (int)$InvReturnYN,
                'IsCOC_Cover' => /*$COC_YN ? $COC_YN :*/ $without_addon ? 0 : (int)$consumable,
                'IsEngGearBox_Cover' => $without_addon ? 0 : (int)$engine_protection,
                'IsEA_Cover' => /*$EA_YN ? $EA_YN : */$without_addon ? 0 : (int)$RSACover,
                'IsEAW_Cover' => /*$EAW_YN ? $EAW_YN : */$without_addon ? 0 : (int)$KeyReplacementYN,
                'IsTyreSecure_Cover' => (!in_array($mmv_data->vehicle_manufacturer, ['HONDA', 'TATA MOTORS LTD']) && ($car_age <= 3) && !$without_addon) ? 1 : 0,
                'NoofUnnamedPerson' => $pa_unnamed_passenger,
                'IsLossofUseDownTimeProt_Cover' => $without_addon ? 0 : $is_loss_of_use_opted,
                'UnnamedPersonSI' => $pa_unnamed_passenger_sa,
                'ElecticalAccessoryIDV' => $electrical_accessories_sa,
                'NonElecticalAccessoryIDV' => $non_electrical_accessories_sa,
                'CPA_Tenure' => $cpa_tenure_renewal, //(($premium_type == 'own_damage') ? '0' : '1'), // By Default CPA will be 1 Year
                'Effectivedrivinglicense' => (($premium_type == 'own_damage') ? 'true' : 'false'),
                'Voluntary_Excess_Discount' => 0, //$voluntary_deductible, // Voluntary Deductible discount is removed.
                'POLICY_TENURE' => (($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') ? '3' : '1'),
                // 'TPPDLimit' => $tppd_cover, as per #23856
                "Owner_Driver_Nominee_Age" => $ownerdrivernomineeage,
                "Owner_Driver_Nominee_Name" => $ownerdrivernomineename,
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

        if($is_pos){
            $premium_request_array['Req_PvtCar']['POSP_CODE'] = !empty($pos_code) ? $pos_code : [];
        }
        if($nilDepreciationCover == '1' && $car_age > 5 && $requestData->applicable_ncb != 0){
            $premium_request_array['Req_PvtCar']['planType'] = 'Essential ZD plan';
        }
        //OLD REQUEST
        //     'TransactionID' => $TRANSACTIONID,
        //     'Req_Renewal'    => [
        //         'Policy_No'  => $policy_no,
        //         'Vehicle_Regn_No' => $registration_number,
        //     ],
        //     'Policy_Details' => [
        //         'PolicyStartDate' => $policy_start_date,
        //         'ProposalDate' => $policy_start_date,
        //         'BusinessType_Mandatary' => $previous_policy_type,//$business_type,
        //         'VehicleModelCode' => $model_code,
        //         'DateofDeliveryOrRegistration' => $vechile_registration_date,
        //         'DateofFirstRegistration' => $vechile_registration_date,
        //         'YearOfManufacture' => Carbon::createFromFormat('d/m/Y',$vechile_registration_date)->format('Y'),
        //         'RTOLocationCode' => $rto_code,
        //         'Vehicle_IDV' => $veh_idv,
        //         'PreviousPolicy_NCBPercentage' => $requestData->previous_ncb,
        //         'PreviousPolicy_PolicyEndDate' => $previous_policy_end_date,
        //         'PreviousPolicy_PolicyClaim' => ($requestData->is_claim == 'N') ? 'NO' : 'YES',
        //         'PreviousPolicy_PolicyNo' => $policy_no,
        //         "EngineNumber" => "TREYTREGREGER",
        //         "FinancierCode" => "",
        //         "ChassisNumber" => "TREYTREGREGERRRRR",
        //         "AgreementType" => "",
        //         "BranchName" => "",
        //         "PreviousPolicy_IsZeroDept_Cover" => $PreviousPolicy_IsZeroDept_Cover,
        //         "PreviousPolicy_IsRTI_Cover" => $PreviousPolicy_IsRTI_Cover,
        //     ],
        //     'Req_PvtCar' => [
        //         'IsLimitedtoOwnPremises' => '0',
        //         'ExtensionCountryCode' => $geoExtension,
        //         'ExtensionCountryName' => '',
        //         'BiFuelType' => ($externalCNGKITSI > 0 ? "CNG" : ""),
        //         'BiFuel_Kit_Value' => $externalCNGKITSI,
        //         'POLICY_TYPE' => (($premium_type == 'own_damage') ? 'OD Only' : (($premium_type == "third_party") ? '' : 'OD Plus TP')), // as per the IC in case of tp only value for POLICY_TYPE will be null
        //         'LLPaiddriver' => $LLtoPaidDriverYN,
        //         'PAPaiddriverSI' => $PAPaidDriverConductorCleanerSI,
        //         'IsZeroDept_Cover' => /*$zero_dep_YN ? $zero_dep_YN :*/ $nilDepreciationCover,
        //         'IsNCBProtection_Cover' => (int)$ncb_protction,
        //         'IsRTI_Cover' => (int)$InvReturnYN,
        //         'IsCOC_Cover' => /*$COC_YN ? $COC_YN :*/ (int)$consumable,
        //         'IsEngGearBox_Cover' => (int)$engine_protection,
        //         'IsEA_Cover' => /*$EA_YN ? $EA_YN : */(int)$RSACover,
        //         'IsEAW_Cover' => /*$EAW_YN ? $EAW_YN : */(int)$KeyReplacementYN,
        //         'IsTyreSecure_Cover' => (int)$tyresecure,
        //         'NoofUnnamedPerson' => (int)$pa_unnamed_passenger,
        //         'IsLossofUseDownTimeProt_Cover' =>(int)$LossOfPersonBelongYN,
        //         'UnnamedPersonSI' => (int)$pa_unnamed_passenger_sa,
        //         'ElecticalAccessoryIDV' => (int)$electrical_accessories_sa,
        //         'NonElecticalAccessoryIDV' => (int)$non_electrical_accessories_sa,
        //         //'CPA_Tenure' => (($premium_type == 'own_damage') ? '0' : ($requestData->business_type == 'newbusiness' ? '3' : '1')),
        //         'CPA_Tenure' => $cpa_tenure_renewal,//(($premium_type == 'own_damage') ? '0' : '1'), // By Default CPA will be 1 Year
        //         'Effectivedrivinglicense' => (($premium_type == 'own_damage') ? 'true' : 'false'),
        //         // 'Voluntary_Excess_Discount' => $voluntary_deductible, // Voluntary Deductible discount is removed.
        //         'POLICY_TENURE' => (($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') ? '3' : '1'),
        //         // 'TPPDLimit' => $tppd_cover, as per #23856
        //         "Owner_Driver_Nominee_Age" => "24",
        //         "Owner_Driver_Nominee_Name" => "Subodh",
        //         "Owner_Driver_Nominee_Relationship" => "1311",
        //         'NumberOfEmployees' => ($requestData->vehicle_owner_type == 'C' ? $mmv_data->seating_capacity  : 0),
        //         'POSP_CODE' => '',
        //         'NoofnamedPerson' => $pa_paid_driver
        //     ],
        // ];
        if(!empty($cpa_tenure_renewal)){
            $premium_request_array['Req_PvtCar']['CPA_Tenure'] = '1';
        }
        else{
            $premium_request_array['Req_PvtCar']['CPA_Tenure'] = '0';
        }
        // dd($premium_request_array);
        $extension_data = [
            'electrical_accessories_sa' => $electrical_accessories_sa,
            'non_electrical_accessories_sa' => $non_electrical_accessories_sa,
            'geoExtension' => $geoExtension,
            'externalCNGKITSI' => $externalCNGKITSI,
            'LLtoPaidDriverYN' => $LLtoPaidDriverYN,
            'PAPaidDriverConductorCleanerSI' => $PAPaidDriverConductorCleanerSI,
            'nilDepreciationCover' => $nilDepreciationCover,
            'ncb_protction' => $ncb_protction,
            'InvReturnYN' => $InvReturnYN,
            'consumable' => $consumable,
            'engine_protection' => $engine_protection,
            'RSACover' => $RSACover,
            'KeyReplacementYN' => $KeyReplacementYN,
            'tyresecure' => $tyresecure,
            'pa_unnamed_passenger' => $pa_unnamed_passenger,
            'LossOfPersonBelongYN' => $LossOfPersonBelongYN,
            'pa_unnamed_passenger_sa' => $pa_unnamed_passenger_sa,
            'salutation' => $salutation,
            'pos' => $pos_data ?? null,
            'pos_code' => $pos_code ?? null,
            'is_pos' => $is_pos ?? null,
            'is_pos_enabled' => $is_pos_enabled,
            'hdfc_pos_code' => $hdfc_pos_code ?? null
        ];
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
            'webservice_id' => $getpremiumresponse['webservice_id'], 'table' => $getpremiumresponse['table'],
            'extension_data' => $extension_data,
        ];
    }

    static function getCalcProposal($proposal_data)
    {
        extract($proposal_data);
        extract($extension_data);

        $prev_policy_end_date = $requestData->previous_policy_expiry_date;
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($prev_policy_end_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $car_age = floor($age / 12);

        $without_addon = ($productData->product_identifier == 'without_addon') ? true : false;

        $PRODUCT_CODE  = $ProductCode;
        $TRANSACTIONID = $transactionid;
        $additionData = [
            'type'              => 'ProposalCalculation',
            'method'            => 'Proposal Calculation',
            'section'           => 'car',
            'requestMethod'     => 'post',
            'productName'       => $productData->product_name . " Renewal",
            'enquiryId'         => $enquiryId,
            'transaction_type'  => 'proposal',
            'SOURCE'            => $SOURCE,
            'CHANNEL_ID'        => $CHANNEL_ID,
            'PRODUCT_CODE'      => $PRODUCT_CODE,
            'CREDENTIAL'        => $CREDENTIAL,
            'TRANSACTIONID'     => $TRANSACTIONID,
            'TOKEN'             => $token,
        ];
        // dd($proposal);
        $proposal_request_array = [
            'TransactionID' => $transactionid, //config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR') ,//$enquiryId,
            'Policy_Details' => [
                //'PolicyStartDate' => date('d/m/Y', strtotime($policy_start_date)),
                //'ProposalDate' => date('d/m/Y'),
                //'BusinessType_Mandatary' => $business_type,
                // 'VehicleModelCode' => $mmv_data->vehicle_model_code,
                // 'DateofDeliveryOrRegistration' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                // 'DateofFirstRegistration' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                // 'YearOfManufacture' => date('Y', strtotime($requestData->vehicle_register_date)),
                // 'RTOLocationCode' => $rto_code,
                'Vehicle_IDV' => $veh_idv,
                // 'PreviousPolicy_CorporateCustomerId_Mandatary' => $proposal->previous_insurance_company, //$prev_policy_company,
                // 'PreviousPolicy_NCBPercentage' => $requestData->previous_ncb,
                // 'PreviousPolicy_PolicyEndDate' => $proposal->prev_policy_expiry_date,
                // 'PreviousPolicy_PolicyClaim' => ($requestData->is_claim == 'N') ? 'NO' : 'YES',
                // 'PreviousPolicy_PolicyNo' => $proposal->previous_policy_number,
                // 'PreviousPolicy_PreviousPolicyType' => (($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage') ? 'Comprehensive Package' : 'TP'),
                // 'Registration_No' => $requestData->business_type == 'newbusiness' ? '' : $proposal->vehicale_registration_number,
                // 'EngineNumber' => $proposal->engine_number,
                // 'ChassisNumber' => $proposal->chassis_number,
                'AgreementType' => ($proposal->is_vehicle_finance == '1') ? $proposal->financer_agreement_type : '',
                'FinancierCode' => ($proposal->is_vehicle_finance == '1') ? $proposal->name_of_financer : '',
                'BranchName' => ($proposal->is_vehicle_finance == '1') ? $proposal->hypothecation_city : '',
                // "PreviousPolicy_IsZeroDept_Cover" => $PreviousPolicy_IsZeroDept_Cover,
                // "PreviousPolicy_IsRTI_Cover" => $PreviousPolicy_IsRTI_Cover
            ],
            'Req_PvtCar' => [
                'POSP_CODE' => '',
                'POLICY_TYPE' => $premium_type == 'own_damage' ? 'OD Only' : ($premium_type == "third_party" ? "" : 'OD Plus TP'),
                'ExtensionCountryCode' => $geoExtension,
                'ExtensionCountryName' => '',
                'BiFuelType' => ($externalCNGKITSI > 0 ? "CNG" : ""),
                'BiFuel_Kit_Value' => $externalCNGKITSI,
                // 'BreakIN_ID' => null,
                // 'BreakInStatus' => null,
                // 'BreakInInspectionFlag' => null,
                // 'BreakinWaiver' => false,
                // 'BreakinInspectionDate' => null,
                'NumberOfEmployees' => ($requestData->vehicle_owner_type == 'C' ? $mmv_data->seating_capacity  : 0),
                //                    "EMIAmount" => "0",
                'LLPaiddriver' => $LLtoPaidDriverYN,
                'PAPaiddriverSI' => $PAPaidDriverConductorCleanerSI,
                'IsZeroDept_Cover' => $without_addon ? 0 : $nilDepreciationCover,
                'IsNCBProtection_Cover' => $without_addon ? 0 : (int)$ncb_protction,
                'IsRTI_Cover' => $without_addon ? 0 : (int)$InvReturnYN,
                'IsCOC_Cover' => $without_addon ? 0 : (int)$consumable,
                'IsEngGearBox_Cover' => $without_addon ? 0 : (int)$engine_protection,
                'IsEA_Cover' => $without_addon ? 0 : (int)$RSACover,
                'IsEAW_Cover' => $without_addon ? 0 : (int)$KeyReplacementYN,
                'IsTyreSecure_Cover' => $without_addon ? 0 : (int)$tyresecure,
                'NoofUnnamedPerson' => (int)$pa_unnamed_passenger,
                'IsLossofUseDownTimeProt_Cover' => (int)$LossOfPersonBelongYN,
                'UnnamedPersonSI' => (int)$pa_unnamed_passenger_sa,
                'ElecticalAccessoryIDV' => (int)$electrical_accessories_sa,
                'NonElecticalAccessoryIDV' => (int)$non_electrical_accessories_sa,
                'CPA_Tenure' => $cpa_tenure_renewal, #($requestData->business_type == 'newbusiness' ? '3' : '1'),
                'Effectivedrivinglicense' => (($premium_type == 'own_damage') ? 'true' : 'false'),
                'Voluntary_Excess_Discount' => 0,//$voluntary_deductible,
                'POLICY_TENURE' => '1',
                // 'TPPDLimit' => $tppd_cover, as per #23856
                "Owner_Driver_Nominee_Name" => $ownerdrivernomineename ?? '',
                "Owner_Driver_Nominee_Age" => $ownerdrivernomineeage ?? '',//($proposal->owner_type == 'I') ? $proposal->nominee_age : "0",
                "Owner_Driver_Nominee_Relationship" => '1311',//(!$premium_type == 'own_damage' || $proposal->owner_type == 'I') ? $proposal->nominee_relationship : "0",
                //                    "Owner_Driver_Appointee_Name" => ($proposal->owner_type == 'I') ? $proposal->nominee_name : "0",
                //                    "Owner_Driver_Appointee_Relationship" => ($proposal->owner_type == 'I') ? $proposal->nominee_relationship : "0",
                'OtherLoadDiscRate' => 0,
                'AntiTheftDiscFlag' => false,
                'HandicapDiscFlag' => false,
                'isTowing_Cover' => 0,
                'Towing_Limit' => null,
                'IsEmiProtector_Cover' => 0,
                'NoOfEmi' => null,
                'EMIAmount' => 0.0,
                'NoofnamedPerson' => 0,
                'namedPersonSI' => 0.0,
                'NamedPersons' => null,
                'AutoMobile_Assoication_No' => '',
                'fuel_type' => $mmv_data->fuel,
                'payAsYouDrive' => null,
                'initialOdometerReading' => null,
                'initialOdometerReadingDate' => null,
                'IsHighProtection_Cover' => 0,
                'HigherTowingLimit' => 0.0,
            ],
            'Customer_Details' => [
                'GC_CustomerID' => '',
                "IsCustomer_modify" => null,
                'Customer_Type' => ($proposal->owner_type == 'I') ? 'Individual' : 'Corporate',
                'Company_Name' => ($proposal->owner_type == 'I') ? '' : $proposal->first_name,
                'Customer_FirstName' => ($proposal->owner_type == 'I') ? $proposal->first_name : $proposal->last_name,
                'Customer_MiddleName' => '',
                'Customer_LastName' => ($proposal->owner_type == 'I') ? (!empty($proposal->last_name) ? $proposal->last_name : '.') : '',
                'Customer_DateofBirth' => date('d/m/Y', strtotime($proposal->dob)),
                'Customer_Email' => $proposal->email,
                'Customer_Mobile' => $proposal->mobile_number,
                'Customer_Telephone' => '',
                'Customer_PanNo' => $proposal->pan_number,
                'Customer_Salutation' => $salutation, #($proposal->owner_type == 'I') ? 'MR' : 'M/S',
                'Customer_Gender' => $proposal->gender,
                'Customer_Perm_Address1' => removeSpecialCharactersFromString($proposal->address_line1, true),
                'Customer_Perm_Address2' => $proposal->address_line2,
                'Customer_Perm_Apartment' => '',
                'Customer_Perm_Street' => '',
                'Customer_Perm_PinCode' => $proposal->pincode,
                'Customer_Perm_PinCodeLocality' => '',
                'Customer_Perm_CityDistrictCode' => $proposal->city_id,
                'Customer_Perm_CityDistrict' => $proposal->city,
                'Customer_Perm_StateCode' => $proposal->state_id,
                'Customer_Perm_State' => $proposal->state,
                'Customer_Mailing_Address1' => $proposal->is_car_registration_address_same == 1 ? removeSpecialCharactersFromString($proposal->address_line1, true) : $proposal->car_registration_address1,
                'Customer_Mailing_Address2' => $proposal->is_car_registration_address_same == 1 ? $proposal->address_line2 : $proposal->car_registration_address2,
                'Customer_Mailing_Apartment' => '',
                'Customer_Mailing_Street' => '',
                'Customer_Mailing_PinCode' => $proposal->is_car_registration_address_same == 1 ? $proposal->pincode : $proposal->car_registration_pincode,
                'Customer_Mailing_PinCodeLocality' => '',
                'Customer_Mailing_CityDistrictCode' => $proposal->is_car_registration_address_same == 1 ? $proposal->city_id : $proposal->car_registration_city_id,
                'Customer_Mailing_CityDistrict' => $proposal->is_car_registration_address_same == 1 ? $proposal->city : $proposal->car_registration_city,
                'Customer_Mailing_StateCode' => $proposal->is_car_registration_address_same == 1 ? $proposal->state_id : $proposal->car_registration_state_id,
                'Customer_Mailing_State' => $proposal->is_car_registration_address_same == 1 ? $proposal->state : $proposal->car_registration_state,
                'Customer_GSTIN_Number' => $proposal->gst_number,
                'BusinessType_Mandatary' => $previous_policy_type,//$business_type,
                'Customer_AnnualIncome' => null,
                'Customer_OrganisationType' => null,
                'Customer_PepStatus' => null,
                'Customer_GSTIN_State' => '',
                'Customer_Professtion' => null,
                'Customer_MaritalStatus' => $proposal->marital_status,
                'Customer_EIA_Number' => null,
                'Customer_IDProof' => null,
                'Customer_IDProofNo' => null,
                'Customer_Nationality' => null,
                'Customer_UniqueRefNo' => null,
                'Customer_GSTDetails' => null,
                'Customer_Pehchaan_id' => '',
            ],
            'Req_Renewal'    => [
                'Policy_No'  => $policy_no,
                'Vehicle_Regn_No' => $registration_number,
            ]
        ];
        if($is_pos){
            $proposal_request_array['Req_PvtCar']['POSP_CODE'] = !empty($pos_code) ? $pos_code : [];
        }
        if($nilDepreciationCover == '1' && $car_age > 5 && $requestData->applicable_ncb != 0){
            $proposal_request_array['Req_PvtCar']['planType'] = 'Essential ZD plan';
        }
        if ($premium_type == 'own_damage') {
            $proposal_request_array['Policy_Details']['PreviousPolicy_TPENDDATE'] = $pp_tp_end_date;#$proposal->tp_start_date
            $proposal_request_array['Policy_Details']['PreviousPolicy_TPSTARTDATE'] = $pp_tp_start_date;
            $proposal_request_array['Policy_Details']['PreviousPolicy_TPINSURER'] = $proposal->tp_insurance_company;//'HDFC ERGO General Insurance Co. Ltd.';
            $proposal_request_array['Policy_Details']['PreviousPolicy_TPPOLICYNO'] = $policy_no;
        }
        $getproposalresponse = getWsData(config('IC.HDFC_ERGO.V1.CAR.RENEWAL.PROPOSAL_CALCULATION_URL'), $proposal_request_array, 'hdfc_ergo', $additionData);
        if (!$getproposalresponse['response']) {
            return [
                'status' => false,
                'premium' => 0,
                'message' => 'Proposal Service Issue',
            ];
        }
        $errortag = json_decode($getproposalresponse['response']);
        return [
            'data' => json_decode($getproposalresponse['response']),
            'proposal_request_array' => $proposal_request_array,
            'errortags' => $errortag->Error
        ];
    }
}
