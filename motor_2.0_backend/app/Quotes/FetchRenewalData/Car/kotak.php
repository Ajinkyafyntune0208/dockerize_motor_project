<?php 

namespace App\Quotes\FetchRenewalData\Car;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class kotak
{
    public static function getRenewalData($enquiryId, &$value)
    {
        include_once app_path() . '/Helpers/CarWebServiceHelper.php';

        $tokenData = getKotakTokendetails('motor', false);
        $tokenReqArray = [
            'vLoginEmailId' => $tokenData['vLoginEmailId'],
            'vPassword' => $tokenData['vPassword'],
        ];

        $data = cache()->remember(config('constants.IcConstants.kotak.END_POINT_URL_TOKEN_KOTAK_MOTOR'), 10, function () use ($tokenReqArray, $tokenData, $enquiryId, $value) {
            return getWsData(config('constants.IcConstants.kotak.END_POINT_URL_TOKEN_KOTAK_MOTOR'), $tokenReqArray, 'kotak', [
                'Key' => $tokenData['vRanKey'],
                'enquiryId' => $enquiryId,
                'requestMethod' => 'post',
                'productName'  => $value['product_name'] . '  Renewal Data Migration',
                'company'  => 'kotak',
                'section' => 'car',
                'method' => 'Token Generation',
                'transaction_type' => 'quote',
            ]);
        });

        if (empty($data['response'] ?? null)) {
            return false;
        }

        $tokenResponse = json_decode($data['response'], true);

        if (($tokenResponse['vErrorMsg'] ?? '') == 'Success' && !empty($tokenResponse['vTokenCode'] ?? null)) {
            $userId = config('constants.IcConstants.kotak.KOTAK_MOTOR_USERID');
            $premiumReqArray = [
                "vPolicyNumber" => $value['previous_policy_number'],
                "vLoginEmailId" => $userId,
                "bIsReCalculate"  => false,
                "vRegistrationNumber"  => "",
                "vChassisNumber"  => $value['chassis_no'],
                "vEngineNumber"  => $value['engine_no'],
                "nFinalIDV"  => 0,
                "nMarketMovement"  => -1,
                "isRoadSideAssistance" => true
            ];

            $data = getWsData(
                config('constants.IcConstants.kotak.KOTAK_MOTOR_FETCH_POLICY_DETAILS_PREMIUM'),
                $premiumReqArray,
                'kotak',
                [
                    'token' => $tokenResponse['vTokenCode'],
                    'headers' => [
                        'vTokenCode' => $tokenResponse['vTokenCode']
                    ],
                    'enquiryId' => $enquiryId,
                    'requestMethod' => 'post',
                    'productName'  => $value['product_name'] . '  Renewal Data Migration',
                    'company'  => 'kotak',
                    'section' => 'car',
                    'method' => 'Fetch Policy Details',
                    'transaction_type' => 'quote',
                ]
            );

            if (empty($data)) {
                return false;
            }

            $response = json_decode($data['response'], true);

            if (empty($response)) {
                return false;
            }
            if (($response['vErrorMessage'] ?? '') != "Success") {
                return false;
            }

            $customerDetails = $response['objCustomerDetails'] ?? null;
            $previousInsurerDetails = $response['objPrevInsurer'] ?? null;

            if (!empty($previousInsurerDetails['vPrevPolicyType'])) {
                if ($previousInsurerDetails['vPrevPolicyType'] == 'ComprehensivePolicy') {
                    $value['prev_policy_type'] = 'COMPREHENSIVE';
                } elseif (in_array($previousInsurerDetails['vPrevPolicyType'], ["OD Only", "1+3"])) {
                    $value['prev_policy_type'] = 'OD';
                } elseif ($previousInsurerDetails['vPrevPolicyType'] == 'LiabilityOnlyPolicy') {
                    $value['prev_policy_type'] = 'TP';
                }
            }

            if (!empty($customerDetails)) {
                $value['communication_address'] = !empty($customerDetails['Permanent_AddressLine1'] ?? null) ? $customerDetails['Permanent_AddressLine1'] : ($value['communication_address'] ?? null);
                $value['communication_address'] = trim($value['communication_address'] . ' ' . ($customerDetails['Permanent_AddressLine2'] ?? ''));
                $value['communication_address'] = trim($value['communication_address'] . ' ' . ($customerDetails['Permanent_AddressLine3'] ?? ''));
                $value['communication_city'] = $customerDetails['Permanent_CustomerCity'] ?? $value['communication_city'] ?? null;
                $value['communication_state'] = $customerDetails['Permanent_CustomerStateName'] ?? $value['communication_state'] ?? null;
                $value['communication_pincode'] = $customerDetails['Permanent_Pincode'] ?? $value['communication_pincode'] ?? null;

                $value['dob'] = !empty($customerDetails['vCustomerDOB'] ?? null) ? date('d-m-Y', strtotime(str_replace('/', '-', $customerDetails['vCustomerDOB']))) : ($value['dob'] ?? null);
                $value['email_address'] = !empty($customerDetails['vCustomerEmail'] ?? null) ? $customerDetails['vCustomerEmail'] : ($value['email_address'] ?? null);
                $value['full_name'] = !empty($customerDetails['vCustomerFullName'] ?? null) ? $customerDetails['vCustomerFullName'] : ($value['full_name'] ?? null);
                $value['gender'] = !empty($customerDetails['vCustomerGender'] ?? null) ? substr($customerDetails['vCustomerGender'], 0, 1) : ($value['gender'] ?? null);
                $value['mobile_no'] = !empty($customerDetails['vCustomerMobile'] ?? null) ? $customerDetails['vCustomerMobile'] : ($value['mobile_no'] ?? null);
                $value['pan_card'] = !empty($customerDetails['vCustomerPanNumber'] ?? null) ? $customerDetails['vCustomerPanNumber'] : ($value['pan_card'] ?? null);
                $value['nominee_name'] = !empty($customerDetails['vCustomerNomineeName'] ?? null) ? $customerDetails['vCustomerNomineeName'] : ($value['nominee_name'] ?? null);
                $value['nominee_age'] = $value['nominee_age'] ?? null;
                $value['relationship_with_nominee'] = !empty($customerDetails['vCustomerNomineeRelationship'] ?? null) ? $customerDetails['vCustomerNomineeRelationship'] : ($value['relationship_with_nominee'] ?? null);
                $value['nominee_dob'] = !empty($customerDetails['vCustomerNomineeDOB'] ?? null) ? date('d-m-Y', strtotime(str_replace('/', '-', $customerDetails['vCustomerNomineeDOB']))) : ($value['nominee_dob'] ?? null);

                if (!empty($customerDetails['vCustomerType'] ?? null)) {
                    $value['owner_type'] = $customerDetails['vCustomerType'] == 'I' ? 'individual' : 'company';
                }
            }
            $value['previous_ncb'] = strlen($response['vPreviousYearNCB'] ?? null) > 0 ? $response['vPreviousYearNCB'] : ($value['previous_ncb'] ?? null);
            $value['registration_date'] = !empty($response['vRegistrationDate'] ?? null) ? date('d-m-Y', strtotime(str_replace('/', '-', $response['vRegistrationDate']))) : ($value['registration_date'] ?? null);

            // Addons
            $value['consumable'] = !empty($response['isConsumableCover'] ?? null) ? $response['isConsumableCover'] : ($value['consumable'] ?? null);
            $value['zero_dep'] = !empty($response['isDepreciationCover'] ?? null) ? $response['isDepreciationCover'] : ($value['zero_dep'] ?? null);
            $value['engine_protector'] = !empty($response['isEngineProtect'] ?? null) ? $response['isEngineProtect'] : ($value['engine_protector'] ?? null);
            $value['key_replacement'] = !empty($response['isKeyReplacement'] ?? null) ? $response['isKeyReplacement'] : ($value['key_replacement'] ?? null);
            $value['loss_of_personal_belonging'] = !empty($response['isLossPersonalBelongings'] ?? null) ? $response['isLossPersonalBelongings'] : ($value['loss_of_personal_belonging'] ?? null);
            $value['ncb_protection'] = !empty($response['isNCBProtect'] ?? null) ? $response['isNCBProtect'] : ($value['ncb_protection'] ?? null);
            $value['return_to_invoice'] = !empty($response['isReturnToInvoice'] ?? null) ? $response['isReturnToInvoice'] : ($value['return_to_invoice'] ?? null);
            $value['rsa'] = !empty($response['isRoadSideAssistance'] ?? null) ? $response['isRoadSideAssistance'] : ($value['rsa'] ?? null);
            $value['tyre_secure'] = !empty($response['isTyreCover'] ?? null) ? $response['isTyreCover'] : ($value['tyre_secure'] ?? null);

            $value['cpa_amount'] = !empty($response['vPACoverForOwnDriver'] ?? null) ? $response['vPACoverForOwnDriver'] : ($value['cpa_amount'] ?? null);

            if (!empty($response['isElectricalAccessoriesChecked'] ?? false) === true) {
                $value['electrical'] = true;
                $value['electrical_si_amount'] = $response['vElectricalAccessoriesSI'] ?? null;
            }

            if (!empty($response['isNonElectricalAccessoriesChecked'] ?? false) === true) {
                $value['not-electrical'] = true;
                $value['not-electrical_si_amount'] = $response['vNonElectricalAccessoriesSI'] ?? 0;
            }

            if (!empty($response['isPACoverUnnamed'] ?? false) === true && !empty($response['vUnNamedSI'])) {
                $value['unnammed_passenger_pa_cover'] = true;
                $value['unnammed_passenger_pa_cover_si_amount'] = $response['vUnNamedSI'] ?? 0;
            }

            if (!empty($response['isPACoverPaidDriver'] ?? false) === true && !empty($response['vSIPaidDriver'])) {
                $value['pa_cover_for_additional_paid_driver'] = true;
                $value['pa_cover_for_additional_paid_driver_si_amount'] = $response['vSIPaidDriver'];
            }

            if (!empty($response['vLegalLiabilityPaidDriverNo'])) {
                $value['ll_paid_driver'] = true;
                $value['ll_paid_driver_si_amount'] = $response['vLegalLiabilityPaidDriverNo'];
            }

            if (!empty($response['vVariantCode'] ?? null)) {
                self::getFyntuneVersionId($value, $response['vVariantCode']);
            }
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
        $path = 'mmv_masters/' . $envFolder . '/';
        $path  = $path . $product . "_model_version.json";
        $mmvData = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($path), true);
        foreach ($mmvData as $mmv) {
            if (($mmv['mmv_kotak'] ?? '') == $code) {
                $value['version_id'] = $mmv['version_id'];
                break;
            }
        }
    }
}