<?php 

namespace App\Quotes\FetchRenewalData\Car;

use Illuminate\Support\Facades\Storage;

class icici_lombard
{
    public static function getRenewalData($enquiryId, &$value)
    {
        include_once app_path() . '/Helpers/CarWebServiceHelper.php';
        $additionData = [
            'requestMethod'     => 'post',
            'type'              => 'tokenGeneration',
            'section'           => 'car',
            'productName'       => $value['product_name'],
            'enquiryId'         => $enquiryId,
            'transaction_type'  => 'quote'
        ];
        
        $tokenParam = [
            'grant_type'    => 'password',
            'username'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_MOTOR'),
            'password'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_MOTOR'),
            'client_id'     => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_MOTOR'),
            'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_MOTOR'),
            'scope'         => 'esbmotor',
        ];
        
        $getResponse = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR'), http_build_query($tokenParam), 'icici_lombard', $additionData);
        $token = $getResponse['response'];
        
        if (!empty($token))
        {
            $token = json_decode($token, true);
            
            if(isset($token['access_token']))
            {
                $accessToken = $token['access_token'];
            }
            else
            {
                return false;
            }
        }
        
        if (in_array($value["prev_policy_type"], ['OD'])) {
            $policyType = "own_damage";
        } else if (in_array($value["prev_policy_type"], ['COMPREHENSIVE', '1OD+3TP', '1OD+5TP', '3OD+3TP', '5OD+5TP'])) {
            $policyType = "comprehensive";
        } else if ($value["prev_policy_type"] == "TP") {
            $policyType = "third_party";
        } else {
            $policyType = "comprehensive";
        }
        
        $corelationId = getUUID($enquiryId);
        
        switch($policyType)
        {
            case "comprehensive":
                $dealId = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR');
                break;
            case "own_damage":
                $dealId = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_OD');
                break;
            case "third_party":
                $dealId = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_TP');
                break;
        }

        $dealId = config('DEAL_ID_ICICI_LOMBARD_TESTING') == '' ? $dealId : config('DEAL_ID_ICICI_LOMBARD_TESTING');
        $fetchPolicyDetails = [
            "PolicyNumber"              => $value['previous_policy_number'],
            "CorrelationId"             => $corelationId,
            "EngineNumberLast5Char"     => substr($value['engine_no'] ?? '', -5),
            "ChassisNumberLast5Char"    => substr($value['chassis_no'] ?? '', -5),
            "DealID"                    => $dealId
        ];

        $additionPremData = [
            'requestMethod'     => 'post',
            'type'              => 'Fetch Policy Details',
            'section'           => 'car',
            'productName'       => $value['product_name'],
            'enquiryId'         => $enquiryId,
            'transaction_type'  => 'quote',
            'token'             => $accessToken,
            'test' => "yes"
        ];

        $url = config('constants.IcConstants.icici_lombard.END_POINT_URL_ICICI_LOMBARD_MOTOR_FETCH_POLICY_DATA');
        $getResponse = getWsData($url, $fetchPolicyDetails, 'icici_lombard', $additionPremData);
        $data = $getResponse['response'];

        $response = json_decode($data, true);

        if (($response['status'] ?? false) == true) {
            $proposalDetails = $response['proposalDetails'][0] ?? $response['proposalDetails'];
            $proposalDetails = array_filter($proposalDetails, fn ($var) => (!empty($var)));

            $vehicleDetails = $response['vehicleDetails'] ?? [];
            $nomineeDetails = array_filter($vehicleDetails, fn ($var) => (!empty($var)));

            $nomineeDetails = $response['nomineeDetails'] ?? [];
            $nomineeDetails = array_filter($nomineeDetails, fn ($var) => (!empty($var)));

            $riskDetails = $proposalDetails['riskDetails'] ?? [];
            $riskDetails = array_filter($riskDetails, fn ($var) => (!empty($var)));

            $previousPolicyDetails = $response['previousPolicyDetails'] ?? [];
            $previousPolicyDetails = array_filter($previousPolicyDetails, fn ($var) => (!empty($var)));

            $generalInformation = $proposalDetails['generalInformation'] ?? [];
            $generalInformation = array_filter($generalInformation, fn ($var) => (!empty($var)));

            $value['registration_date'] =  null;
            if (isset($generalInformation['registrationDate'])) {
                $value['registration_date'] = date('d-m-Y', strtotime(str_replace('/','-',$generalInformation['registrationDate'])));
            }

            if (isset($generalInformation['customerType'])) {
                $value['owner_type'] = strtolower($generalInformation['customerType']) == 'corporate' ? 'company' : strtolower($generalInformation['customerType']);
            }

            $value['email_address'] = null;
            $value['full_name'] = null;
            $value['communication_pincode'] = null;
            $value['financier_agreement_type'] = null;
            $value['financier_name'] = null;

            $value['engine_no'] = $vehicleDetails['engineNumber'] ?? $value['engine_no'] ?? null;
            $value['chassis_no'] = $vehicleDetails['chassisNumber'] ?? $value['chassis_no'] ?? null;
            $value['vehicle_registration_number'] = $vehicleDetails['registrationNumber'] ?? $value['vehicle_registration_number'] ?? null;
            $value['vehicle_manufacture_year'] = $generalInformation['manufacturingYear'] ?? $value['vehicle_manufacture_year'] ?? null;

            if (isset($previousPolicyDetails['previousPolicyEndDate'])) {
                $value['previous_policy_end_date'] = date('d-m-Y', strtotime($previousPolicyDetails['previousPolicyEndDate']));
            }
            $value['previous_ncb'] = $previousPolicyDetails['bonusonPreviousPolicy'] ?? $value['previous_ncb'] ?? null;
            $value['previous_claim_status'] = ($generalInformation['claimsonPreviousPolicy'] ?? null) > 0 ? "Y" : $value['previous_claim_status'] ?? null;
            
            $value['nominee_name'] = $nomineeDetails[0]['nomineeName'] ?? $value['nominee_name'] ?? null;
            $value['nominee_age'] = $nomineeDetails[0]['nomineeAge'] ?? $value['nominee_age'] ?? null;
            $value['relationship_with_nominee'] = $nomineeDetails[0]['nomineeRelationship'] ?? $value['relationship_with_nominee'] ?? null;
            $value['nominee_dob'] = $value['nominee_dob'] ?? null;
            
            $value['engine_protector'] = $riskDetails['engineProtect'] ?? $value['engine_protector'] ?? null;
            $value['key_replacement'] = $riskDetails['keyProtect'] ?? $value['key_replacement'] ?? null;
            $value['zero_dep'] = $riskDetails['zeroDepreciation'] ?? $value['zero_dep'] ?? null;
            $value['loss_of_personal_belonging'] = $riskDetails['lossOfPersonalBelongings'] ?? $value['loss_of_personal_belonging'] ?? null;
            $value['return_to_invoice'] = $riskDetails['returnToInvoice'] ?? $value['return_to_invoice'] ?? null;
            $value['tyre_secure'] = $riskDetails['tyreProtect'] ?? $value['tyre_secure'] ?? null;
            $value['consumable'] = $riskDetails['consumables'] ?? $value['consumable'] ?? null;
            $value['rsa'] = $riskDetails['roadSideAssistance'] ?? $value['rsa'] ?? null;
            $value['ncb_protection'] = $value['ncb_protection'] ?? null;

            $value['not-electrical'] = $riskDetails['nonElectricalAccessories'] ?? $value['not-electrical'] ?? null;
            $value['electrical'] = $riskDetails['electricalAccessories'] ?? $value['electrical'] ?? null;
            // $value['external_bifuel_cng_lpg'] =  $value['external_bifuel_cng_lpg'] ?? null;


            $value['pa_cover_for_additional_paid_driver'] = $riskDetails['paCoverForPaidDriver'] ?? $value['pa_cover_for_additional_paid_driver'] ?? null;
            $value['unnammed_passenger_pa_cover'] = $riskDetails['paCoverForUnNamedPassenger'] ?? $value['unnammed_passenger_pa_cover'] ?? null;
            $value['ll_paid_driver'] = $riskDetails['paidDriver'] ?? $value['ll_paid_driver'] ?? null;
            $value['geogarphical_extension'] = $riskDetails['geographicalExtensionTP'] ?? $value['geogarphical_extension'] ?? null;

            $value['cpa_amount'] = $riskDetails['paCoverForOwnerDriver'] ?? $value['cpa_amount'] ?? null;
            $value['cpa_opt-out_reason'] = $value['cpa_opt-out_reason'] ?? null;

            self::getFyntuneVersionId($value, $vehicleDetails['vehicleModelCode']);
            return true;
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
            $path = 'mmv_masters/'.$envFolder.'/';
            $path  = $path . $product . "_model_version.json";
            $mmvData = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($path), true);
            foreach ($mmvData as $mmv) {
                if (($mmv['mmv_icici_lombard'] ?? '') == $code) {
                    $value['version_id'] = $mmv['version_id'];
                    break;
                }
            }
    }
}