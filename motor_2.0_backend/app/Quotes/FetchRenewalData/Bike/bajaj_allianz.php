<?php 

namespace App\Quotes\FetchRenewalData\Bike;

use Illuminate\Support\Facades\Storage;

class bajaj_allianz
{
    public static function getRenewalData($enquiryId, &$value)
    {
        include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

        $policyData = [
            "userid" => config('constants.IcConstants.bajaj_allianz.BAJAJ_ALLIANZ_RENEWAL_USER_ID'),
            "password"=> config('constants.IcConstants.bajaj_allianz.BAJAJ_ALLIANZ_RENEWAL_PASSWORD'),
            "weomotpolicyin" => [
                "registrationno"=> str_replace('-','',$value['vehicle_registration_number']),
                "prvpolicyref"=> $value['previous_policy_number']
            ],
            "motextracover"=> [],
            "custdetails"=> []
        ];
        
        $fetchUrl = config('constants.IcConstants.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_FETCH_RENEWAL');

        $response = getWsData($fetchUrl, $policyData, 'bajaj_allianz', [
            'section' => 'bike',
            'method' => 'get_renewal_data',
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'productName' => $value['product_name'],
            'transaction_type' => 'quote'
        ]);
        $data = $response['response'];
        $response = json_decode($data);

        if (isset($response->errorcode) && $response->errorcode == 0) {
            $policy = $response->weomotpolicyinout ?? null;
            $customer = $response->custdetailsout ?? null;
            $extra = $response->motextracoverout ?? null;
            $covers = $response->motextracoverout ?? null;

            $value['vehicle_registration_number'] = (!empty($policy->registrationno ?? '')) ? $policy->registrationno : $value['vehicle_registration_number']  ?? null;
            $value['registration_date'] = (!empty($policy->registrationdate ?? '')) ? date('Y-m-d', strtotime($policy->registrationdate)) : $value['registration_date']  ?? null;
            $value['vehicle_manufacture_year'] = (!empty($policy->yearmanf ?? '')) ? $policy->yearmanf : $value['vehicle_manufacture_year'] ?? null;
            $value['vehicle_registration_city'] = (!empty($policy->registrationlocation ?? '')) ? $policy->registrationlocation : $value['vehicle_registration_city']  ?? null;
            $value['chassis_no'] = (!empty($policy->engineno ?? '')) ? $policy->engineno : $value['chassis_no'] ?? null;
            $value['engine_no'] = (!empty($policy->chassisno ?? '')) ? $policy->chassisno : $value['engine_no'] ?? null;
            $value['previous_ncb'] = (!empty($policy->prvncb ?? '')) ? $policy->prvncb : $value['previous_ncb'] ?? null;


            $value['previous_policy_number'] = (!empty($policy->prvpolicyref ?? '')) ? $policy->prvpolicyref : $value['previous_policy_number'];

            if (!empty($policy->prvexpirydate ?? null)) {
                $value['previous_policy_end_date'] = date('Y-m-d', strtotime($policy->prvexpirydate));
            }

            if ($customer ?? false) {
                $value['full_name'] = str_replace('  ', ' ', trim($customer->firstname.' '.$customer->middlename. ' '.$customer->surname));
                $value['email_address'] = (!empty($customer->email ?? '')) ? $customer->email : $value['email_address'] ?? null;
                $value['mobile_no'] = (!empty($customer->mobile ?? '')) ? $customer->mobile : $value['mobile_no'] ?? null;
                $value['owner_type'] = (!empty($policy->partnertype ?? '')) ? ($policy->partnertype == 'P' ? 'individual' : 'company') : $value['owner_type'] ?? null;
                $value['dob'] = (!empty($customer->dob ?? '')) ? date('d-m-Y', strtotime($customer->dob)) : $value['dob'] ?? null;
                $value['communication_address'] = str_replace('  ', ' ', trim($customer->addline1.' '.$customer->addline2));
                $value['communication_pincode'] = (!empty($customer->pincode ?? '')) ? $customer->pincode : $value['communication_pincode'] ?? null;
                $value['communication_state'] = (!empty($customer->addline5 ?? '')) ? $customer->addline5 : $value['communication_state'] ?? null;
                $value['communication_city'] = (!empty($customer->addline3 ?? '')) ? $customer->addline3 : $value['communication_city'] ?? null;
                $value['occupation'] = (!empty($customer->profession ?? '')) ? $customer->profession : $value['occupation'] ?? null;
            }

            if (!empty($extra->extrafield1 ?? null)) {
                $env = config('app.env');
                if ($env == 'local') {
                    $envFolder = 'uat';
                } elseif ($env == 'test') {
                    $envFolder = 'production';
                } elseif ($env == 'live') {
                    $envFolder = 'production';
                }
                $product = "bike";
                $path = 'mmv_masters/' . $envFolder . '/';
                $path  = $path . $product . "_model_version.json";
                $mmvData = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($path), true);
                foreach ($mmvData as $mmv) {
                    if (($mmv['mmv_bajaj_allianz'] ?? '') == $extra->extrafield1) {
                        $value['version_id'] = $mmv['version_id'];
                        break;
                    }
                }
            }
            return true;
        }
        return false;
    }
}