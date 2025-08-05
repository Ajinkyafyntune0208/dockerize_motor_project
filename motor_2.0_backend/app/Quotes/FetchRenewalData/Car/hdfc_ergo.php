<?php

namespace App\Quotes\FetchRenewalData\Car;

use DateTime;
use App\Models\PreviousInsurerList;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;


class hdfc_ergo
{
    public static function getRenewalData($enquiryId, &$value)
    {
        include_once app_path() . '/Helpers/CarWebServiceHelper.php';
        // hypothecation_city
        // TPPolicyNumber
        // TPPolicyInsurer
        // owner_type
        // pan and other owner details which we are not getting from fetch

        if (in_array($value["prev_policy_type"], ['OD'])) {
            $policyType = "own_damage";
        } else if (in_array($value["prev_policy_type"], ['COMPREHENSIVE', '1OD+3TP', '1OD+5TP', '3OD+3TP', '5OD+5TP'])) {
            $policyType = "comprehensive";
        } else if ($value["prev_policy_type"] == "TP") {
            $policyType = "third_party";
        } else {
            $policyType = "comprehensive";
        }

        $transactionid = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 10);

        $SOURCE        = config('IC.HDFC_ERGO.V1.CAR.RENEWAL.SOURCE');
        $CHANNEL_ID    = config('IC.HDFC_ERGO.V1.CAR.RENEWAL.CHANNELID');
        $CREDENTIAL    = config('IC.HDFC_ERGO.V1.CAR.RENEWAL.CREDENTIAL');
        $ProductCode   = '2311';

        //token
        $PRODUCT_CODE  = $ProductCode;
        $TRANSACTIONID = $transactionid;
        $additionData = [
            'type'              => 'gettoken',
            'method'            => 'tokenGeneration',
            'section'           => 'car',
            'productName'       => $value['product_name'] . " Renewal Data Migration",
            'enquiryId'         => $enquiryId,
            'transaction_type'  => 'quote',
            'PRODUCT_CODE'      => $PRODUCT_CODE,
            'SOURCE'            => $SOURCE,
            'CHANNEL_ID'        => $CHANNEL_ID,
            'TRANSACTIONID'     => $TRANSACTIONID,
            'CREDENTIAL'        => $CREDENTIAL,
        ];
        $token_gen = getWsData(config('IC.HDFC_ERGO.V1.CAR.RENEWAL.TOKEN_GENERATION_URL'), '', 'hdfc_ergo', $additionData);
        $token_data = json_decode($token_gen['response'], TRUE);
        $auth_token = !empty($token_data['Authentication']['Token']) ? $token_data['Authentication']['Token'] : false;

        //fetch    
        $additionData = [
            'type'              => 'getrenewal',
            'method'            => 'Renewal Fetch Policy',
            'section'           => 'car',
            'requestMethod'     => 'post',
            'productName'       => $value['product_name'] . " Renewal Data Migration Fetch",
            'enquiryId'         => $enquiryId,
            'transaction_type'  => 'quote',
            'PRODUCT_CODE'      => $PRODUCT_CODE,
            'SOURCE'            => $SOURCE,
            'CHANNEL_ID'        => $CHANNEL_ID,
            'CREDENTIAL'        => $CREDENTIAL,
            'TRANSACTIONID'     => $TRANSACTIONID,
            'TOKEN'             => $auth_token,
        ];
        $renewal_fetch_data_array = [
            'TransactionID'     => $TRANSACTIONID,
            'Req_Renewal' => [
                "Policy_no" => $value['previous_policy_number'],
            ]
        ];
        $renewal_fetch_data = getWsData(config('IC.HDFC_ERGO.V1.CAR.RENEWAL.FETCH_GENERATION_URL'), $renewal_fetch_data_array, 'hdfc_ergo', $additionData);
        $renewal_fetch_data = json_decode($renewal_fetch_data['response']);

        if (!empty($renewal_fetch_data)) {
            if (empty($renewal_fetch_data->Error)) {
                $fetch = $renewal_fetch_data->Resp_RE;
                $customer = $renewal_fetch_data->Customer_Details;

                $value['vehicle_registration_number'] = $fetch->Registration_No;
                $value['registration_date'] = (str_replace('/', '-', $fetch->DateofDeliveryOrRegistration));
                $value['vehicle_manufacture_year'] = $fetch->YearOfManufacture;
                $value['vehicle_registration_city'] = $fetch->RTOLocationDesc;
                $value['is_financed'] = !empty($fetch->FinancierCode) ? "Yes" : 'No';
                $value['financier_agreement_type'] = $fetch->AgreementType;
                $value['financier_name'] = $fetch->FinancierName;
                // $value['hypothecation_city'] = $vehicle->FinancierAddress;
                $value['engine_no'] = strtoupper($fetch->EngineNumber);
                $value['chassis_no'] = strtoupper($fetch->ChassisNumber);
                $value['previous_policy_number'] = $fetch->PreviousPolicy_PolicyNo;
                $value['previous_claim_status'] = !empty($fetch->PreviousPolicy_PolicyClaim) ? 'Y' : 'N';
                $value['idv'] = round($fetch->IDV);
                $value['previous_ncb'] = $fetch->PreviousPolicy_NCBPercentage;
                $value['previous_tp_policy_number'] = $fetch->PreviousPolicy_PolicyNo;      // TP policy number will be same according to Shyam.
                if(!empty($fetch->PreviousPolicy_PolicyEndDate)){
                    $value['previous_policy_end_date'] = date('d-m-Y', strtotime(str_replace('/','-',$fetch->PreviousPolicy_PolicyEndDate)));
                }
                if(!empty($fetch->PreviousPolicy_PolicyStartDate)){
                    $value['previous_policy_start_date'] = date('d-m-Y', strtotime(str_replace('/','-',$fetch->PreviousPolicy_PolicyStartDate)));
                }

                if (!empty($fetch->PreviousPolicy_TPStartDate ?? null)) {
                    $value["previous_tp_start_date"] = date('d-m-Y',strtotime(str_replace('/','-', $fetch->PreviousPolicy_TPStartDate)));
                }

                if (!empty($fetch->PreviousPolicy_TPEndDate ?? null)) {
                    $value["previous_tp_end_date"] = (str_replace('/', '-', $fetch->PreviousPolicy_TPEndDate));
                }

                $value['full_name'] = $customer->Customer_Name;
                $value['email_address'] = $customer->CustomerEmail;
                $value['mobile_no'] = $customer->CustomerMobile;
                // $value['owner_type'] = strtolower($clientDetails->ClientType) == 'corporate' ? 'company' : 'individual';  /*MISSING*/
                // $value['gender'] = ($clientDetails->Gender ?? '') == '0' ? 'M' : (($clientDetails->Gender ?? '') == '1' ? 'F' : null);
                // $value['dob'] = str_replace('/', '-', $clientDetails->DOB);
                // $value['pan_card'] = $clientDetails->PanNo;
                // $value['occupation'] = $clientDetails->OccupationID;
                $value['marital_status'] = ($customer->Owner_Driver_Nominee_Relationship ?? '') == 'Spouse' ? 'Married' : 'Single';
                $value['communication_address'] = ($customer->MailingAddress1 ?? null) . ' ' . ($customer->MailingAddress1 ?? null);
                $value['communication_pincode'] = $customer->MailingPinCode ?? null;
                $value['communication_address'] = trim($value['communication_address']);
                $value['communication_state'] = $customer->MailingState ?? null;
                $value['communication_city'] = $customer->MailingCityDistrict ?? null;


                $value['relationship_with_nominee'] = $value['nominee_name'] = $value['nominee_age'] = $value['nominee_dob'] = null;
                $planName = null;

                // Addons - > LOPB COVER MISSING, Bifuel missing, Elec Non-elec missing, 
                $value['zero_dep'] = !empty($fetch->IsZD_Cover) ? $fetch->IsZD_Cover : null;
                $value['rsa'] =  !empty($fetch->IsEA_Cover) ? $fetch->IsEA_Cover : null;
                $value['engine_protector'] =  !empty($fetch->IsEngGearBox_Cover) ? $fetch->IsEngGearBox_Cover : null;
                $value['return_to_invoice'] =  !empty($fetch->IsRTI_Cover) ? $fetch->IsRTI_Cover : null;
                $value['consumable'] =  !empty($fetch->IsCOC_Cover) ? $fetch->IsCOC_Cover : null;
                $value['key_replacement'] = !empty($fetch->IsEAW_Cover) ? $fetch->IsEAW_Cover : null;
                $value['cpa_amount'] =  !empty($fetch->IsPAOwnerDriver_Cover) ? $fetch->IsPAOwnerDriver_Cover : null;
                $value['ncb_protection'] = !empty($fetch->IsNCB_Cover) ? $fetch->IsNCB_Cover : null;
                if (!empty($fetch->IsPAOwnerDriver_Cover)) {
                    $value['nominee_name'] = $fetch->Owner_Driver_Nominee_Name ?? $value['nominee_name'];
                    $value['nominee_dob'] = $fetch->PACoverToOwner->NomineeDOB ?? null;
                    $value['relationship_with_nominee'] = $fetch->Owner_Driver_Nominee_Relationship ?? $value['relationship_with_nominee'];
                }
                $value['anti_theft'] = !empty($fetch->IsAntiTheftDisc) ? $fetch->IsAntiTheftDisc : null;
                $value['ll_paid_driver'] = !empty($fetch->IsPaidDriver_Cover) ? $fetch->IsPaidDriver_Cover : null;
  
                if (!empty($fetch->IsUnnamedPerson_Cover) && ($fetch->IsUnnamedPerson_Cover > 0)) {
                    $value['unnammed_passenger_pa_cover'] = $fetch->IsUnnamedPerson_Cover;
                    $value['unnammed_passenger_pa_cover_si_amount'] = $fetch->UnnamedPersonSI;
                }
                if (!empty($fetch->PAPaiddriverSI) && ($fetch->PAPaiddriverSI > 0)){
                    $value['pa_cover_for_additional_paid_driver'] = $fetch->PAPaiddriverSI;
                    $value['pa_cover_for_additional_paid_driver_si_amount'] = $fetch->PAPaiddriverSI ?? null;
                }

                self::getFyntuneVersionId($value, $fetch->VehicleModelCode);
                return true;
            }
        }
    
        return false;
    }

    public static function getFyntuneVersionId(&$value, $code)
    {
        $env = config('app.env');
        if ($env == 'local') {
            $envFolder = 'uat';
        } elseif ($env == 'test') {
            $envFolder = 'production';
        } elseif ($env == 'live') {
            $envFolder = 'production';
        }
        $product = "motor";
        $path = 'mmv_masters/' . $envFolder . '/';
        $path  = $path . $product . "_model_version.json";
        $mmvData = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($path), true);
        foreach ($mmvData as $mmv) {
            if (($mmv['mmv_hdfc_ergo'] ?? '') == $code) {
                $value['version_id'] = $mmv['version_id'];
                break;
            }
        }
    }
}
