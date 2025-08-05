<?php

namespace App\Helpers\Broker;

use App\Models\PolicyDetails;
use App\Models\WealthMakerApiLogs;
use Illuminate\Support\Facades\Http;

class WealthMakerApi
{
    public static function tokenGeneration()
    {
        try {
            $tokenUrl = config('constants.brokerConstant.bajaj.WEALTH_MAKER_TOKEN_URL');
            $tokenRequest = [
                'Source' => config('constants.brokerConstant.bajaj.WEALTH_MAKER_SOURCE'),
                'UserId' => config('constants.brokerConstant.bajaj.WEALTH_MAKER_USER_ID'),
                'Password' => config('constants.brokerConstant.bajaj.WEALTH_MAKER_PASSWORD')
            ];

            $response = httpRequestNormal($tokenUrl, 'POST', $tokenRequest, [], [], [], false)['response'];
            
            if (!empty($response) && !empty($response['Data'][0]['Token'] ?? null)) {
                return ['status' => true, 'token'=> $response['Data'][0]['Token']];
            }
        } catch (\Throwable $th) {
            info($th);
        }
       return ['status' =>  false];
    }

    public static function dataPush($data)
    {
        $status = 'Failed';
        $response = null;
        try {
            
            if (!empty($data['registration_date'] ?? '')) {
                $data['registration_date'] = str_replace('/', '-', $data['registration_date']);
                $data['registration_date'] = date('d/m/Y', strtotime($data['registration_date']));
            }

            if (!empty($data['dob'] ?? '')) {
                $data['dob'] = str_replace('/', '-', $data['dob']);
                $data['dob'] = date('d/m/Y', strtotime($data['dob']));
            }

            //do not push to wealth maker if policy number already exists
            if (!empty($data['previous_policy_number'])) {
                $policy_details = PolicyDetails::where('policy_number', $data['previous_policy_number'])
                ->whereNotNull('policy_number')
                ->select('policy_number', 'proposal_id')
                ->first();
                if (!empty($policy_details)) {
                    return true;
                }
            }

            $dataPush = [
                'POLICY_NO' => $data['previous_policy_number'] ?? '',
                'PLY_ISSUE_DT' => !empty($data["previous_policy_end_date"] ?? '') ? date('d/m/Y', strtotime($data["previous_policy_end_date"])) : '',
                'LOGIN_DT' => !empty($data["previous_policy_start_date"] ?? '') ? date('d/m/Y', strtotime($data["previous_policy_start_date"])) : '',
                'APP_NO' => !empty($data['previous_policy_number'] ?? null) ? $data['previous_policy_number'] : null,
                'P_NAME' => !empty($data['full_name'] ?? null) ? $data['full_name'] : '',
                'I_NAME' => !empty($data['full_name'] ?? null) ? $data['full_name'] : '',
                'N_NAME' => !empty($data['nominee_name'] ?? null) ? $data['nominee_name'] : '',
                'P_DOB' => !empty($data['dob'] ?? '') ? $data['dob'] : '',
                'I_DOB' => !empty($data['dob'] ?? '') ? $data['dob'] : '',
                'IADD1' => !empty($data['communication_address'] ?? null) ? $data['communication_address'] : '',
                'IADD2' => '',
                'ICITY' => !empty($data['communication_city'] ?? '') ? $data['communication_city'] : '',
                'IPIN' => !empty($data['communication_pincode'] ?? '') ? $data['communication_pincode'] : '',
                'MOBILE' => !empty($data['mobile_no'] ?? '') ? $data['mobile_no'] : '',
                'COMPANY_CD' => $data['previous_insurer_company'] ?? '',
                'PLAN_NO' => !empty($data['prev_policy_type'] ?? '') ? $data['prev_policy_type'] : '',
                'SA' => null,
                'PLY_TERM' => null,
                'PREM_TERM' => null,
                'ENGINE_NO' => !empty($data['engine_no'] ?? '') ? $data['engine_no'] : null,
                'CHASIS_NO' => !empty($data['chassis_no'] ?? '') ? $data['chassis_no'] : null,
                'NO_OF_PASSENGER' => null,
                'REGISTRATION_DATE' => !empty($data['registration_date'] ?? '') ? $data['registration_date'] : '',
                'PAYMENT_MODE' => '',
                'MANUFACTURER' => '',
                'MODEL_NAME' => '',
                'VARIENT_NAME' => '',
                'PAID_DRIVER' => null,
                'OWNER_DRIVER' => null,
                'UNNAMED_PA' => null,
                'MANUF_YEAR' => !empty($data['vehicle_manufacture_year'] ?? '') ? (explode('-', $data['vehicle_manufacture_year'])[1] ?? '') : null,
                'THIRD_PARTY_PREM' => null
            ];
            
            $token = self::tokenGeneration();

            if ($token['status'] ?? false) {
                $dataPush['TOKEN'] = $token['token'];
            }

            $url = config('constants.brokerConstant.bajaj.WEALTH_MAKER_DATA_PUSH_URL');
            $response = httpRequestNormal($url, 'POST', $dataPush, [], [], [], false)['response'];
            if (!empty($response) && ($response['status'] ?? null) == 'Success') {
                $status = 'Success';
            }
        } catch (\Throwable $th) {
            info($th);
        }

        WealthMakerApiLogs::updateOrCreate([
            'renewal_data_migration_status_id' => $data['migration_id'],
        ], [
            'user_product_journey_id' => $data['enquiry_id'],
            'request' => json_encode($dataPush),
            'response' => json_encode($response),
            'status' => $status
        ]);

        return true;
    }
}