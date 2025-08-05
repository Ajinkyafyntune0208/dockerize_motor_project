<?php 

namespace App\Quotes\FetchRenewalData\Car;

use DateTime;
use App\Models\PreviousInsurerList;
use Illuminate\Support\Facades\Storage;

class reliance
{
    public static function getRenewalData($enquiryId, &$value)
    {
        include_once app_path() . '/Helpers/CarWebServiceHelper.php';

        if (in_array($value["prev_policy_type"], ['OD'])) {
            $policyType = "own_damage";
        } else if (in_array($value["prev_policy_type"], ['COMPREHENSIVE', '1OD+3TP', '1OD+5TP', '3OD+3TP', '5OD+5TP'])) {
            $policyType = "comprehensive";
        } else if ($value["prev_policy_type"] == "TP") {
            $policyType = "third_party";
        } else {
            $policyType = "comprehensive";
        }

        $tpOnly = $policyType == 'third_party';
        $userID = (($tpOnly == true) && !empty(config('constants.IcConstants.reliance.TP_USERID_RELIANCE'))) ? config('constants.IcConstants.reliance.TP_USERID_RELIANCE') : config('constants.IcConstants.reliance.USERID_RELIANCE');
        $sourceSystemID = (($tpOnly == true) && !empty(config('constants.IcConstants.reliance.TP_SOURCE_SYSTEM_ID_RELIANCE')) )? config('constants.IcConstants.reliance.TP_SOURCE_SYSTEM_ID_RELIANCE') : config('constants.IcConstants.reliance.SOURCE_SYSTEM_ID_RELIANCE');
        $authToken = (($tpOnly == true) && !empty(config('constants.IcConstants.reliance.TP_AUTH_TOKEN_RELIANCE')) ) ? config('constants.IcConstants.reliance.TP_AUTH_TOKEN_RELIANCE') : config('constants.IcConstants.reliance.AUTH_TOKEN_RELIANCE');
        

        $renewalFetchArrray = [
            'PrevPolicyNumber' => $value['previous_policy_number'],//'920222123110003941',
            'EngineNo' => '',
            'ChassisNo' => '',
            'RegistrationNo' => getRegisterNumberWithHyphen($value['vehicle_registration_number']),// 'MH-01-AZ-3455',
            'PrevPolicyEndDate' => '',
            'ProductCode' => '',
            'SourceSystemID' => $userID,
            'AuthToken' => $authToken,
            'UserID' => $sourceSystemID,
        ];


        $gerResponse = getWsData(
            config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_FETCH_RENEWAL'),
            $renewalFetchArrray,
            'reliance',
            [
                'root_tag'      => 'RenwalPolicy',
                'section'       => 'car',
                'method'        => 'Renewal Fetch',
                'requestMethod' => 'post',
                'enquiryId'     => $enquiryId,
                'productName'   => $value['product_name'] . " Renewal Data Migration",
                'transaction_type'    => 'quote',
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                ]
            ]
        );
        $response = $gerResponse['response'];

        if ($response) {
            $response = json_decode($response);
            if (!isset($response->PolicyDetails->ErrorMessages->ErrMessages)) {
                $vehicle = $response->PolicyDetails->Vehicle;
                $coverDetails = $response->PolicyDetails->CoverDetails->CoverList ?? [];
                $clientDetails = $response->PolicyDetails->ClientDetails;
                $policy = $response->PolicyDetails->Policy;
                $premium = $response->PolicyDetails->Premium;
                $ncbEligibility = $response->PolicyDetails->NCBEligibility;
                $previousInsuranceDetails = $response->PolicyDetails->PreviousInsuranceDetails;

                $value['vehicle_registration_number'] = $vehicle->Registration_Number;
                $value['registration_date'] = date('d-m-Y', strtotime(str_replace('/','-',$vehicle->Registration_date)));
                $value['vehicle_manufacture_year'] = date('m-Y', strtotime(str_replace('/','-',$vehicle->ManufactureDate)));
                $value['vehicle_registration_state'] = $vehicle->RTOstate;
                $value['vehicle_registration_city'] = $vehicle->RTOLocation;
                $value['is_financed'] = $vehicle->IsVehicleHypothicated == 'true' ? "Yes" : 'No';
                $value['financier_agreement_type'] = $vehicle->FinanceType;
                $value['financier_name'] = $vehicle->FinancierName;
                $value['hypothecation_city'] = $vehicle->FinancierAddress;
                $value['engine_no'] = strtoupper($vehicle->EngineNo);
                $value['chassis_no'] = strtoupper($vehicle->Chassis);

                $value['previous_policy_number'] = $previousInsuranceDetails->PrevYearPolicyNo;
                $value['previous_policy_end_date'] = str_replace('/', '-', $previousInsuranceDetails->PrevYearPolicyEndDate);
                $value['previous_policy_start_date'] = str_replace('/', '-', $previousInsuranceDetails->PrevYearPolicyStartDate);
                $value['previous_claim_status'] = $vehicle->IsClaimedLastYear == 'true' ? 'Y' : 'N';
                $value['idv'] = round($vehicle->IDV);
                $value['previous_ncb'] = $ncbEligibility->PreviousNCB;
                $value['proposal_no'] = $policy->ProposalNo;

                if (!empty($policy->TPPolicyNumber ?? null)) {
                    $value["previous_tp_policy_number"] = $policy->TPPolicyNumber;
                }

                if (!empty($policy->TPPolicyStartDate ?? null)) {
                    $value["previous_tp_start_date"] = DateTime::createFromFormat('Y-m-d', $policy->TPPolicyStartDate)->format('d-m-Y');
                }

                if (!empty($policy->TPPolicyEndDate ?? null)) {
                    $value["previous_tp_end_date"] = DateTime::createFromFormat('Y-m-d', $policy->TPPolicyEndDate)->format('d-m-Y');
                }

                if (!empty($policy->TPPolicyInsurer ?? null)) {
                    $insurer = PreviousInsurerList::where('code', $policy->TPPolicyInsurer)
                        ->where('company_alias', 'reliance')
                        ->first();
                    $value["previous_tp_insurer_company"] = $insurer['name'];
                }

                $value['full_name'] = $clientDetails->ForeName. ' '.$clientDetails->MidName.' '. $clientDetails->LastName;
                $value['email_address'] = $clientDetails->EmailID;
                $value['mobile_no'] = $clientDetails->MobileNo;
                $value['owner_type'] = strtolower($clientDetails->ClientType) == 'corporate' ? 'company' : 'individual';
                $value['gender'] = ($clientDetails->Gender ?? '') == '0' ? 'M' : (($clientDetails->Gender ?? '') == '1' ? 'F' : null);
                $value['dob'] = str_replace('/', '-', $clientDetails->DOB);
                $value['pan_card'] = $clientDetails->PanNo;
                $value['occupation'] = $clientDetails->OccupationID;
                $value['marital_status'] = ($clientDetails->MaritalStatus ?? '') == '1952' ? 'Single' : (($clientDetails->MaritalStatus ?? '') == '1951' ? 'Married' : null);
                $value['communication_address'] = ($clientDetails->ClientAddress->CommunicationAddress->Address1 ?? null) .' '.($clientDetails->ClientAddress->CommunicationAddress->Address2 ?? null).' '.($clientDetails->ClientAddress->CommunicationAddress->Address3 ?? null);
                $value['communication_pincode'] = $clientDetails->ClientAddress->CommunicationAddress->Pincode ?? null;
                $value['communication_address'] = trim($value['communication_address']);
                $value['communication_state'] = $clientDetails->ClientAddress->CommunicationAddress->StateID ?? null;
                $value['communication_city'] = $clientDetails->ClientAddress->CommunicationAddress->CityID ?? null;

                $value['relationship_with_nominee'] = $value['nominee_name'] = $value['nominee_age'] = $value['nominee_dob'] = null;
                $planName = null;
                foreach ($coverDetails as $cover) {
                    if (($cover->IsChecked ?? 'false') == 'true') {
                        switch ($cover->CoverName) {
                            case 'Liability to Paid Driver':
                                $value['ll_paid_driver'] = abs($cover->CoverPremium);
                                break;
                            case 'Electrical Accessories':
                                $value['electrical'] = abs($cover->CoverPremium);
                                $value['electrical_si_amount'] = $cover->SumInsured ?? null;
                                break;
                            case 'PA to Owner Driver':
                                $value['cpa_amount'] = abs($cover->CoverPremium);
                                if (!empty($cover->PACoverToOwner)) {
                                    $value['nominee_name'] = $cover->PACoverToOwner->NomineeName ?? $value['nominee_name'];
                                    $value['nominee_dob'] = $cover->PACoverToOwner->NomineeDOB ?? $value['nominee_dob'];
                                    if (!empty($value['nominee_dob'])) {
                                        $value['nominee_dob'] = str_replace('/', '-', $value['nominee_dob']);
                                    }
                                    $value['relationship_with_nominee'] = $cover->PACoverToOwner->NomineeRelationship ?? $value['relationship_with_nominee'];
                                }
                                break;
                            case 'Nil Depreciation':
                                $value['zero_dep'] = abs($cover->CoverPremium);
                                break;
                            case 'Non Electrical Accessories':
                                $value['not-electrical'] = abs($cover->CoverPremium);
                                $value['not-electrical_si_amount'] = $cover->SumInsured ?? null;
                                break;
                            case 'PA to Unnamed Passenger':
                                $value['unnammed_passenger_pa_cover'] = abs($cover->CoverPremium);
                                $value['unnammed_passenger_pa_cover_si_amount'] = $cover->SumInsured ?? null;
                                break;
                            case 'PA to Paid Driver':
                                $value['pa_cover_for_additional_paid_driver'] = abs($cover->CoverPremium);
                                $value['pa_cover_for_additional_paid_driver_si_amount'] = $cover->SumInsured ?? null;
                                break;
                            case 'Anti-Theft Device':
                                $value['anti_theft'] = abs($cover->CoverPremium);
                                break;
                            case 'Voluntary Deductible':
                                $value['voluntary_excess'] = abs($cover->CoverPremium);
                                $value['voluntary_excess_si'] = $cover->SumInsured ?? null;
                                break;
                            case 'Geographical Extension':
                                $value['geogarphical_extension'] = abs($cover->CoverPremium);
                                $value['geogarphical_extension_si_amount'] = $cover->SumInsured ?? null;
                                break;

                            case 'Geo Extension':
                                $value['geogarphical_extension'] = abs($cover->CoverPremium);
                                $value['geogarphical_extension_si_amount'] = $cover->SumInsured ?? null;
                                break;
                            case 'Liability to Paid Driver' :
                                $value['ll_paid_driver'] = abs($cover->CoverPremium);
                                $value['ll_paid_driver_si_amount'] = $cover->SumInsured ?? null;
                                break;
                            case 'Bifuel Kit':
                                $value['external_bifuel_cng_lpg'] = abs($cover->CoverPremium);
                                $value['external_bifuel_cng_lpg_si_amount'] = $cover->SumInsured ?? null;
                                break;

                            case 'Bifuel Kit TP':
                                $value['external_bifuel_cng_lpg'] = abs($cover->CoverPremium);
                                $value['external_bifuel_cng_lpg_si_amount'] = $cover->SumInsured ?? null;
                                break;
                            
                            case 'Geo Extension' :
                                $value['geogarphical_extension'] = true;
                                break;

                            case 'TPPD':
                                $value['tpp_discount'] = abs($cover->CoverPremium);
                                break;
                            case 'Secure Plus':
                                $planName = ($cover->IsChecked ?? false) == 'true' ? $cover->CoverName : $planName;
                                break;
                            case 'Secure Premium':
                                $planName = ($cover->IsChecked ?? false) == 'true' ? $cover->CoverName : $planName;
                                break;
                        }
                    }
                }

                if (!empty($planName)) {
                    switch ($planName) {
                        case 'Secure Plus':
                            $value['zero_dep'] = true;
                            $value['consumable'] = true;
                            $value['key_replacement'] = true;
                            $value['engine_protector'] = true;
                            $value['loss_of_personal_belonging'] = true;
                            break;

                        case 'Secure Premium':
                            $value['zero_dep'] = true;
                            $value['consumable'] = true;
                            $value['key_replacement'] = true;
                            $value['engine_protector'] = true;
                            $value['loss_of_personal_belonging'] = true;
                            $value['tyre_secure'] = true;
                            break;
                    }
                }
                self::getFyntuneVersionId($value, $vehicle->VehicleModelID);
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
            if (($mmv['mmv_reliance'] ?? '') == $code) {
                $value['version_id'] = $mmv['version_id'];
                break;
            }
        }
    }
}