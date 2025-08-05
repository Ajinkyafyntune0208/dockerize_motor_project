<?php 

namespace App\Quotes\FetchRenewalData\Car;

use Illuminate\Support\Facades\Storage;

class godigit
{
    public static function getRenewalData($enquiryId, &$value)
    {
        if(config('IC.GODIGIT.V2.CAR.RENEWAL.ENABLE') == 'Y')  return self::getOneApiRenewalData($enquiryId, $value);
        
        include_once app_path() . '/Helpers/CarWebServiceHelper.php';

        $fetchUrl = config('constants.IcConstants.godigit.GODIGIT_BIKE_MOTOR_FETCH_RENEWAL_URL').$value['previous_policy_number'];

        $getresponse = getWsData($fetchUrl,[], 'godigit', [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'get',
            'productName'   => $value['product_name'] . " Renewal Data Migration",
            'company'           => 'godigit',
            'section'           => 'car',
            'method'            => 'Fetch Policy Details',
            'transaction_type'  => 'quote',
            'webUserId' =>config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID'),
            'password' =>config('constants.IcConstants.godigit.GODIGIT_PASSWORD'),
        ]);
        $data = $getresponse['response'];
        $response = json_decode($data);

        if (isset($response->error->errorCode) && $response->error->errorCode == '0') {
            $previousInsurer = $response->previousInsurer ?? null;
            $vehicle = $response->vehicle ?? null;
            $coverages = $response->contract->coverages ?? [];
            $person = $response->persons[0] ?? null;

            $value['previous_policy_end_date'] = (!empty($response->contract->endDate ?? null)) ? $response->contract->endDate : ($value['previous_policy_end_date'] ?? null);
            $value['previous_claim_status'] = (!empty($previousInsurer->isClaimInLastYear ?? null)) ? ($previousInsurer->isClaimInLastYear == true ? 'Y' : 'N') : null;
            $value['previous_ncb'] = $previousInsurer->previousNoClaimBonusValue ?? null;

            $value['idv'] = !empty($vehicle->vehicleIDV->idv ?? null) ? $vehicle->vehicleIDV->idv : null;
            $value['vehicle_registration_number'] = $vehicle->licensePlateNumber ?? null;
            $value['registration_date'] = $vehicle->registrationDate ?? null;
            $value['vehicle_manufacture_year'] = !empty($vehicle->manufactureDate ?? null) ? date('m-Y', strtotime($vehicle->manufactureDate)) : null;
            $value['chassis_no'] = !empty($vehicle->vehicleIdentificationNumber ?? null) ? strtoupper($vehicle->vehicleIdentificationNumber) : null;
            $value['engine_no'] = !empty($vehicle->engineNumber ?? null) ? strtoupper($vehicle->engineNumber) : null;

            foreach ($coverages as $key => $cover) {
                switch ($key) {
                    case 'addons':
                        foreach ($cover as $key => $addon) {
                            if (($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE')) {
                                switch ($key) {
                                    case 'partsDepreciation':
                                        $value['zero_dep'] = (str_replace("INR ", "", ($addon->netPremium ?? '')));
                                        break;
    
                                    case 'roadSideAssistance':
                                        $value['rsa'] = (str_replace("INR ", "", ($addon->netPremium ?? '')));
                                        break;
    
                                    case 'engineProtection':
                                        $value['engine_protector'] = (str_replace("INR ", "", ($addon->netPremium ?? '')));
                                        break;
    
                                    case 'returnToInvoice':
                                        $value['return_to_invoice'] = (str_replace("INR ", "", ($addon->netPremium ?? '')));
                                        break;
    
                                    case 'consumables':
                                        $value['consumable'] = (str_replace("INR ", "", ($addon->netPremium ?? '')));
                                        break;
    
                                    case 'personalBelonging':
                                        $value['loss_of_personal_belonging'] = (str_replace("INR ", "", ($addon->netPremium ?? '')));
                                        break;
    
                                    case 'keyAndLockProtect':
                                        $value['key_replacement'] = (str_replace("INR ", "", ($addon->netPremium ?? '')));
                                        break;
                                }
                            }
                            
                        }
                        break;
                    case 'personalAccident';
                        if (isset($cover->selection) && ($cover->selection == 1) && (isset($cover->netPremium))) {
                            $value['cpa_amount'] = (str_replace("INR ", "", $cover->netPremium));
                        }
                        break;
                    case 'accessories':
                        foreach ($cover as $key => $addon) {
                            if (($addon->selection == 'true' && ($addon->coverAvailability ?? "") == 'AVAILABLE')) {
                                switch ($key) {
                                    case 'cng':
                                        $value['external_bifuel_cng_lpg'] =  (str_replace("INR ", "", $addon->netPremium));
                                        $value['external_bifuel_cng_lpg_si_amount'] =  $addon->insuredAmount ?? null;
                                        break;
                                    case 'electrical':
                                        $value['electrical'] =  (str_replace("INR ", "", $addon->netPremium));
                                        $value['electrical_si_amount'] =  $addon->insuredAmount ?? null;
                                        break;
                                    case 'nonElectrical':
                                        $value['not-electrical'] =  (str_replace("INR ", "", $addon->netPremium));
                                        $value['not-electrical_si_amount'] =  $addon->insuredAmount ?? null;
                                        break;
                                }
                            }
                        }
                        break;
                    case 'unnamedPA':
                        foreach ($cover as $key => $addon) {
                            if (($addon->selection == 'true' && ($addon->coverAvailability ?? "") == 'AVAILABLE')) {
                                switch ($key) {
                                    case 'unnamedPaidDriver':
                                        $value['pa_cover_for_additional_paid_driver'] =  (str_replace("INR ", "", $addon->netPremium));
                                        $value['pa_cover_for_additional_paid_driver_si_amount'] =  $addon->insuredAmount ?? null;
                                        break;
                                }
                            }
                        }
                        break;
                    case 'isGeoExt':
                        if ($cover == true) {
                            $value['geogarphical_extension'] = '';
                            $value['geogarphical_extension_si_amount'] = null;
                        }
                        break;
                    case 'legalLiability' :
                        foreach ($cover as $cover => $addon) {
                            if ($addon->selection == 1) {
                                switch ($cover) {
                                    case 'paidDriverLL':
                                        $value['ll_paid_driver'] = (str_replace("INR ", "", $addon->netPremium));
                                        $value['ll_paid_driver_si_amount'] = $addon->insuredAmount ?? null;
                                        break;
                                }
                            }
                        }
                    break;
                    
                }
            }

            if (isset($person)) {
                if (($person->personType ?? '') == 'COMPANY') {
                    $value['full_name'] = trim($person->companyName ?? '');
                } else {
                    $value['full_name'] = trim($person->firstName. ' '. $person->lastName);
                    $value['dob'] = $person->dateOfBirth;
                    $value['gender'] = substr($person->gender, 0, 1);
                    $value['gender'] = empty($value['gender']) ? null : $value['gender'];
                }
                $value['owner_type'] = !empty($person->personType) ? strtolower($person->personType) : null;
    
                $address = $person->addresses[0] ?? null;
                if (isset($address)) {
                    $value['communication_address'] = !empty($address->streetNumber ?? null) ? $address->streetNumber : ($value['communication_address'] ?? null);
                    $value['communication_address'] = trim($value['communication_address'].' '.($address->street ?? ''));
                    $value['communication_city'] = $address->city ?? $value['communication_city'] ?? null;
                    $value['communication_state'] = $address->state ?? $value['communication_state'] ?? null;
                    $value['communication_pincode'] = $address->pincode ?? $value['communication_pincode'] ?? null;
                }
    
                $communications = $person->communications ?? [];
    
                foreach ($communications as $comm) {
                    if ($comm->communicationType == 'EMAIL') {
                        $value['email_address'] = $comm->communicationId;
                    }
    
                    if ($comm->communicationType == 'MOBILE') {
                        $value['mobile_no'] = $comm->communicationId;
                    }
                }
            }

            $value['nominee_name'] = $value['nominee_name'] ?? null;
            $value['nominee_age'] = $value['nominee_age'] ?? null;
            $value['relationship_with_nominee'] = $value['relationship_with_nominee'] ?? null;
            $value['nominee_dob'] = $value['nominee_dob'] ?? null;

            $value['financier_agreement_type'] = null;
            $value['financier_name'] = null;

            self::getFyntuneVersionId($value, $vehicle->vehicleMaincode);
            return true;
        }
        return false;
    }

    public static function getOneApiRenewalData($enquiryId, &$value){
        include_once app_path() . '/Helpers/CarWebServiceHelper.php';
        
        $tokenrequest  = [
            "username" => config('IC.GODIGIT.V2.USERNAME'),
            "password" => config('IC.GODIGIT.V2.PASSWORD') 
        ];

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'productName'       => $value['product_name'] . " Renewal Data Migration" ,
            'company'           => 'godigit',
            'section'           => 'car',
            'method'            => 'token generation',
            'transaction_type'  => 'quote',
            'headers' => [
                'Content-Type'   => "application/json",
                "Connection"    => "Keep-Alive",
                'Accept'        => "application/json",
            ]

        ];
        $tokenservice = getWsData(config('IC.GODIGIT.V2.TOKEN_GENERATION_URL'), $tokenrequest, 'godigit', $additional_data);

        $tokenserviceresponse  = $tokenservice['response'];
        $tokenservicejson = json_decode($tokenserviceresponse, true);
        if (empty($tokenservicejson)) {
           return false;
        }

        $requestdata = [
            "motorMotorrenewalgetquoteApi" => [
                "queryParam" =>  [
                    "policyNumber" => $value['previous_policy_number']
                ]
            ]
        ];

        if(config('IC.GODIGIT.V2.CAR.REMOVE_GODIGIT_IDENTIFIER') == 'Y'){
            $requestdata = $requestdata['motorMotorrenewalgetquoteApi'];
        }
              
        $fetchUrl = config('IC.GODIGIT.V2.CAR.END_POINT_URL');
        
        $getresponse = getWsData($fetchUrl,$requestdata,'godigit',
        [
            'enquiryId' => $enquiryId,
            'requestMethod' =>'post',
            'productName'   => $value['product_name'] . " Renewal Data Migration",
            'company'  => 'godigit', 
            'section' => 'car',
            'method'            => 'Fetch Policy Details',
            'transaction_type' => 'quote',
            'type'              => 'renewal',
            'headers' => [
                'Content-Type'  => "application/json",
                "Connection"    => "Keep-Alive",
                'Authorization' =>  "Bearer ".$tokenservicejson['access_token'],
                'Accept'        => "application/json",
                "integrationId" => config("IC.GODIGIT.V2.CAR.FETCH_INTEGRATION_ID") 
            ]
        ]);

        $data = $getresponse['response'];
        $response = json_decode($data); 
        if (isset($response->error->errorCode) && $response->error->errorCode == '0') {
            $previousInsurer = $response->previousInsurer ?? null;
            $vehicle = $response->vehicle ?? null;
            $coverages = $response->contract->coverages ?? [];
            $person = $response->persons[0] ?? null;

            $value['previous_policy_end_date'] = (!empty($response->contract->endDate ?? null)) ? $response->contract->endDate : ($value['previous_policy_end_date'] ?? null);
            $value['previous_claim_status'] = (!empty($previousInsurer->isClaimInLastYear ?? null)) ? ($previousInsurer->isClaimInLastYear == true ? 'Y' : 'N') : null;
            $value['previous_ncb'] = $previousInsurer->previousNoClaimBonusValue ?? null;

            $value['idv'] = !empty($vehicle->vehicleIDV->idv ?? null) ? $vehicle->vehicleIDV->idv : null;
            $value['vehicle_registration_number'] = $vehicle->licensePlateNumber ?? null;
            $value['registration_date'] = $vehicle->registrationDate ?? null;
            $value['vehicle_manufacture_year'] = !empty($vehicle->manufactureDate ?? null) ? date('m-Y', strtotime($vehicle->manufactureDate)) : null;
            $value['chassis_no'] = !empty($vehicle->vehicleIdentificationNumber ?? null) ? strtoupper($vehicle->vehicleIdentificationNumber) : null;
            $value['engine_no'] = !empty($vehicle->engineNumber ?? null) ? strtoupper($vehicle->engineNumber) : null;

            foreach ($coverages as $key => $cover) {
                switch ($key) {
                    case 'addons':
                        foreach ($cover as $key => $addon) {
                            if (($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE')) {
                                switch ($key) {
                                    case 'partsDepreciation':
                                        $value['zero_dep'] = (str_replace("INR ", "", ($addon->netPremium ?? '')));
                                        break;
    
                                    case 'roadSideAssistance':
                                        $value['rsa'] = (str_replace("INR ", "", ($addon->netPremium ?? '')));
                                        break;
    
                                    case 'engineProtection':
                                        $value['engine_protector'] = (str_replace("INR ", "", ($addon->netPremium ?? '')));
                                        break;
    
                                    case 'returnToInvoice':
                                        $value['return_to_invoice'] = (str_replace("INR ", "", ($addon->netPremium ?? '')));
                                        break;
    
                                    case 'consumables':
                                        $value['consumable'] = (str_replace("INR ", "", ($addon->netPremium ?? '')));
                                        break;
    
                                    case 'personalBelonging':
                                        $value['loss_of_personal_belonging'] = (str_replace("INR ", "", ($addon->netPremium ?? '')));
                                        break;
    
                                    case 'keyAndLockProtect':
                                        $value['key_replacement'] = (str_replace("INR ", "", ($addon->netPremium ?? '')));
                                        break;
                                }
                            }
                            
                        }
                        break;
                    case 'personalAccident';
                        if (isset($cover->selection) && ($cover->selection == 1) && (isset($cover->netPremium))) {
                            $value['cpa_amount'] = (str_replace("INR ", "", $cover->netPremium));
                        }
                        break;
                    case 'accessories':
                        foreach ($cover as $key => $addon) {
                            if (($addon->selection == 'true' && ($addon->coverAvailability ?? "" )== 'AVAILABLE')) {
                                switch ($key) {
                                    case 'cng':
                                        $value['external_bifuel_cng_lpg'] =  (str_replace("INR ", "", $addon->netPremium));
                                        $value['external_bifuel_cng_lpg_si_amount'] =  $addon->insuredAmount ?? null;
                                        break;
                                    case 'electrical':
                                        $value['electrical'] =  (str_replace("INR ", "", $addon->netPremium));
                                        $value['electrical_si_amount'] =  $addon->insuredAmount ?? null;
                                        break;
                                    case 'nonElectrical':
                                        $value['not-electrical'] =  (str_replace("INR ", "", $addon->netPremium));
                                        $value['not-electrical_si_amount'] =  $addon->insuredAmount ?? null;
                                        break;
                                }
                            }
                        }
                        break;
                    case 'unnamedPA':
                        foreach ($cover as $key => $addon) {
                            if (($addon->selection == 'true' && ($addon->coverAvailability ?? "") == 'AVAILABLE')) {
                                switch ($key) {
                                    case 'unnamedPaidDriver':
                                        $value['pa_cover_for_additional_paid_driver'] =  (str_replace("INR ", "", $addon->netPremium));
                                        $value['pa_cover_for_additional_paid_driver_si_amount'] =  $addon->insuredAmount ?? null;
                                        break;
                                }
                            }
                        }
                        break;
                    case 'isGeoExt':
                        if ($cover == true) {
                            $value['geogarphical_extension'] = '';
                            $value['geogarphical_extension_si_amount'] = null;
                        }
                        break;
                    case 'legalLiability' :
                        foreach ($cover as $cover => $addon) {
                            if ($addon->selection == 1) {
                                switch ($cover) {
                                    case 'paidDriverLL':
                                        $value['ll_paid_driver'] = (str_replace("INR ", "", $addon->netPremium));
                                        $value['ll_paid_driver_si_amount'] = $addon->insuredAmount ?? null;
                                        break;
                                }
                            }
                        }
                    break;
                    
                }
            }

            if (isset($person)) {
                if (($person->personType ?? '') == 'COMPANY') {
                    $value['full_name'] = trim($person->companyName ?? '');
                } else {
                    $value['full_name'] = trim($person->firstName. ' '. $person->lastName);
                    $value['dob'] = $person->dateOfBirth;
                    $value['gender'] = substr($person->gender, 0, 1);
                    $value['gender'] = empty($value['gender']) ? null : $value['gender'];
                }
                $value['owner_type'] = !empty($person->personType) ? strtolower($person->personType) : null;
    
                $address = $person->addresses[0] ?? null;
                if (isset($address)) {
                    $value['communication_address'] = !empty($address->streetNumber ?? null) ? $address->streetNumber : ($value['communication_address'] ?? null);
                    $value['communication_address'] = trim($value['communication_address'].' '.($address->street ?? ''));
                    $value['communication_city'] = $address->city ?? $value['communication_city'] ?? null;
                    $value['communication_state'] = $address->state ?? $value['communication_state'] ?? null;
                    $value['communication_pincode'] = $address->pincode ?? $value['communication_pincode'] ?? null;
                }
    
                $communications = $person->communications ?? [];
    
                foreach ($communications as $comm) {
                    if ($comm->communicationType == 'EMAIL') {
                        $value['email_address'] = $comm->communicationId;
                    }
    
                    if ($comm->communicationType == 'MOBILE') {
                        $value['mobile_no'] = $comm->communicationId;
                    }
                }
            }

            $value['nominee_name'] = $value['nominee_name'] ?? null;
            $value['nominee_age'] = $value['nominee_age'] ?? null;
            $value['relationship_with_nominee'] = $value['relationship_with_nominee'] ?? null;
            $value['nominee_dob'] = $value['nominee_dob'] ?? null;

            $value['financier_agreement_type'] = null;
            $value['financier_name'] = null;
            
            self::getFyntuneVersionId($value, $vehicle->vehicleMaincode);
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
            if (($mmv['mmv_godigit'] ?? '') == $code) {
                $value['version_id'] = $mmv['version_id'];
                break;
            }
        }
    }
}