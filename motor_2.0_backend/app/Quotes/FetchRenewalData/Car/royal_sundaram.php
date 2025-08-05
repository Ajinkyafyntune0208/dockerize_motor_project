<?php 

namespace App\Quotes\FetchRenewalData\Car;

use Illuminate\Support\Facades\Storage;

class royal_sundaram
{
    public static function getRenewalData($enquiryId, &$value)
    {
        include_once app_path() . '/Helpers/CarWebServiceHelper.php';
        $expiryDate = date('d/m/Y', strtotime($value['previous_policy_end_date']));
        $fetchUrl = config('constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_FETCH_POLICY_DETAILS').'?policyNumber='.$value['previous_policy_number'].'&expiryDate='.$expiryDate.'&lob=motor';
        $getResponse = getWsData($fetchUrl, $fetchUrl, 'royal_sundaram', [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'get',
            'productName'   => $value['product_name'] . " Renewal Data Migration",
            'company'           => 'royal_sundaram',
            'section'           => 'car',
            'method'            => 'Fetch Policy Details',
            'transaction_type'  => 'quote'
        ]);
        $data = $getResponse['response'];

        $response = json_decode($data, true);
        if(isset($response['statusCode']) && $response['statusCode'] == 'S-0001') {
            $value['previous_policy_number'] = $response['policyNumber'];

            $value['previous_ncb'] = null;
            $value['previous_claim_status'] = $response['rnlNcb'] > 0 ? 'N' : 'Y';

            
            $value['previous_claim_status'] = null;
            $value['previous_policy_end_date'] = str_replace('/', '-', $response['prevExpiryDate']);

            $value['vehicle_registration_number'] = $response['vehicleRegistrationNumber'];
            $value['registration_date'] = str_replace('/', '-', $response['registrationDate']);
            if (!empty($value['registration_date'])) {
                $value['vehicle_manufacture_year'] = date('m-Y', strtotime($value['registration_date']));
            }
            // $value['vehicle_manufacture_year'] = $response['manufacturingYear'];
            $value['chassis_no'] = $response['chassisNumber'];
            $value['engine_no'] = $response['engineNumber'];

            $value['full_name'] = $response['proposerName'];
            $value['email_address'] = $response['emailId'] ?? $value['email_address'] ?? '';
            $value['mobile_no'] = $response['mobileNo'];

            $value['nominee_name'] = $response['nomineeName'];
            $value['nominee_age'] = $response['nomineeAge'];
            $value['nominee_dob'] = null;
            $value['relationship_with_nominee'] = $response['nomineeRelation'];

            $value['communication_address'] = $response['address1']. ' '.($response['address2'] ?? ''). ' '.($response['address3'] ?? ''). ' '.($response['address4'] ?? '');
            $value['communication_pincode'] = $response['pincode'];
            $value['communication_state'] = $response['state'];
            $value['communication_city'] = $response['city'];

            /*foreach (($response['addonCoverages'] ?? []) as $cover) {
                if ($cover['premium'] <= 0) {
                    continue;
                }
                switch ($cover['name']) {
                    case 'TyreCoverClause':
                        $value['tyre_secure'] = round($cover['premium']);
                        break;
                    case 'DepreciationWaiver':
                        $value['zero_dep'] = round($cover['premium']);
                        break;
                    case 'KeyReplacementCover':
                        $value['key_replacement'] = round($cover['premium']);
                        break;
                    case 'LossofBaggage':
                        $value['loss_of_personal_belonging'] = round($cover['premium']);
                        break;
                    case 'NCBProtectorCover':
                        $value['ncb_protection'] = round($cover['premium']);
                        break;
                    case 'InvoicePrice':
                        $value['return_to_invoice'] = round($cover['premium']);
                        break;
                    case 'AggravationCover':
                        $value['engine_protector'] = round($cover['premium']);
                        break;
                }
            }*/


            foreach (($response['coverages'] ?? []) as $cover) {
                if ($cover['premium'] <= 0) {
                    continue;
                }
                switch ($cover['name']) {
                    case 'TyreCoverClause':
                        $value['tyre_secure'] = round($cover['premium']);
                        break;
                    case 'VPC_CompulsoryPA':
                        $value['cpa_amount'] = round($cover['premium']);
                        break;
                    case 'DepreciationWaiver':
                        $value['zero_dep'] = round($cover['premium']);
                        break;
                    case 'KeyReplacementCover':
                        $value['key_replacement'] = round($cover['premium']);
                        break;
                    case 'LossofBaggage':
                        $value['loss_of_personal_belonging'] = round($cover['premium']);
                        break;
                    case 'NCBProtectorCover':
                        $value['ncb_protection'] = round($cover['premium']);
                        break;
                    case 'InvoicePrice':
                        $value['return_to_invoice'] = round($cover['premium']);
                        break;
                    case 'AggravationCover':
                        $value['engine_protector'] = round($cover['premium']);
                        break;
                    case 'VPC_ElectAccessories':
                        $value['electrical'] = round($cover['premium']);
                        $value['electrical_si_amount'] = !empty($cover['limit'] ?? 0) ? round($cover['limit']) : null;
                        break;
                    case 'NonElectricalAccessories':
                        $value['not-electrical'] = round($cover['premium']);
                        $value['not-electrical_si_amount'] = !empty($cover['limit'] ?? 0) ? round($cover['limit']) : null;
                        break;
                    case 'VPC_PAPaidDriver':
                        $value['ll_paid_driver'] = round($cover['premium']);
                        $value['ll_paid_driver_si_amount'] = !empty($cover['limit'] ?? 0) ? round($cover['limit']) : null;
                        break;
                    case 'VPC_WLLDriver':
                        $value['ll_paid_driver'] = round($cover['premium']);
                        $value['ll_paid_driver_si_amount'] = !empty($cover['limit'] ?? 0) ? round($cover['limit']) : null;
                        break;
                    case 'GeoExtension':
                        $value['geogarphical_extension'] = round($cover['premium']);
                        $value['geogarphical_extension_si_amount'] = !empty($cover['limit'] ?? 0) ? round($cover['limit']) : null;
                        break;
                    case 'EnhancedPAPaidDriverCover':
                        $value['pa_cover_for_additional_paid_driver'] = round($cover['premium']);
                        $value['pa_cover_for_additional_paid_driver_si_amount'] = !empty($cover['limit'] ?? 0) ? round($cover['limit']) : null;
                        break;
                    case 'VPC_PAUnnamed':
                        $value['unnammed_passenger_pa_cover'] = round($cover['premium']);
                        $value['unnammed_passenger_pa_cover_si_amount'] = !empty($cover['limit'] ?? 0) ? round($cover['limit']) : null;
                        break;

                    case 'VPC_CNGLPGforOD':
                        $value['external_bifuel_cng_lpg'] = round($cover['premium']);
                        $value['external_bifuel_cng_lpg_si_amount'] = !empty($cover['limit'] ?? 0) ? round($cover['limit']) : null;
                        break;

                    case 'VPC_CNG_LPG':
                        $value['external_bifuel_cng_lpg'] = round($cover['premium']);
                        $value['external_bifuel_cng_lpg_si_amount'] = !empty($cover['limit'] ?? 0) ? round($cover['limit']) : null;
                        break;
                }
            }
            if (!empty(($response['vehicleModelCode'] ?? ''))) {
                self::getFyntuneVersionId($value, $response['vehicleModelCode']);
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
            if (($mmv['mmv_royal_sundaram'] ?? '') == $code) {
                $value['version_id'] = $mmv['version_id'];
                break;
            }
        }
    }
}