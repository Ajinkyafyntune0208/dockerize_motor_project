<?php

namespace App\Helpers\Broker;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DataUploadHelper
{
    public static function validationForBcl(Request  $request, $validationCriteria)
    {
        $validationCriteria['policies.*.seller_code'] = ['required']; 
        $validationCriteria['policies.*.seller_type'] = ['required', 'in:P,E,Partner'];

        $additonalValidations = [
            'policies.*.vehicle_registration_number' => [
                'nullable'
            ],
            'policies.*.prev_policy_type' => [
                'nullable',
                'in:OD,COMPREHENSIVE,TP'
            ],
            'policies.*.previous_policy_end_date' => [
                'nullable',
                'date_format:Y-m-d'
            ],
            'policies.*.engine_no' => [
                'nullable'
            ],
            'policies.*.chassis_no' => [
                'nullable'
            ],

            'policies.*.previous_policy_number' => [
                'nullable'
            ]

        ];

        $validationCriteria = array_merge($validationCriteria, $additonalValidations);
        
        $validator = Validator::make($request->all(), $validationCriteria);

        $validator->after(function ($validator) use ($request) {
            $policies = $request->input('policies', []);
            

            foreach ($policies as $index => $policy) {
                $hasPolicyNumber = !empty($policy['previous_policy_number']);
                $action = strtolower($policy['action']);

                if ($action == 'migrate') {
                    if ($hasPolicyNumber) {
                        if (empty($policy['vehicle_registration_number'])) {
                            $validator->errors()->add("policies.$index.vehicle_registration_number", "The vehicle registration number is required.");
                        }

                        if (empty($policy['prev_policy_type'])) {
                            $validator->errors()->add("policies.$index.prev_policy_type", "The previous policy type is required.");
                        }

                        if (empty($policy['previous_policy_end_date'])) {
                            $validator->errors()->add("policies.$index.previous_policy_end_date", "The previous policy end date is required.");
                        }

                        if (empty($policy['previous_insurer_company'])) {
                            $validator->errors()->add("policies.$index.previous_insurer_company", "The previous insurer company is required.");
                        }

                        if (!empty($policy['previous_insurer_company']) && in_array($policy['previous_insurer_company'], [
                            'kotak',
                            'icici_lombard'
                        ])) {

                            if (empty($policy['engine_no'])) {
                                $validator->errors()->add("policies.$index.engine_no", "The engine no is required.");
                            }
                            if (empty($policy['chassis_no'])) {
                                $validator->errors()->add("policies.$index.chassis_no", "The chassis no is required.");
                            }
                        }
                    } else {
                        if (empty($policy['secondary_unique_key'])) {
                            $validator->errors()->add("policies.$index.secondary_unique_key", "The secondary unique key is required.");
                        }
                        if (empty($policy['mobile_no'])) {
                            $validator->errors()->add("policies.$index.mobile_no", "The mobile no is required.");
                        }
                    }
                } else {
                    if (empty($policy['previous_policy_number'])) {
                        $validator->errors()->add("policies.$index.previous_policy_number", "The previous policy number is required.");
                    }
                }
            }
        });

        if ($validator->fails()) {
            $errors = $validator->errors();
            $messages = $errors->getMessages();
            $errorMessages = [];
            foreach ($messages as $key => $error_messages) {
                foreach ($error_messages as $error_message) {
                    $row_no = explode('.', $key);
                    $row_no = $row_no[1] ?? $row_no[0];
                    $policy_row_no = "." . ($request->policies[$row_no]['policy_row_no'] ?? '') . ".";
                    $row_no = "." . $row_no . ".";
                    $errorMessages[str_replace($row_no, $policy_row_no, $key)][] = str_replace($row_no, $policy_row_no, $error_message);
                }
            }

            return [
                "status" => false,
                "message" => $errorMessages,
            ];
        }

        return [
            'status' => true
        ];
    }

    public static function generateUserId($mobileNumber)
    {
        try {
            $response = httpRequestNormal(config('constants.motorConstant.BROKER_USER_CREATION_API'), 'POST', [
                'mobile_no' => $mobileNumber
            ]);

            return $response['response']['user_id'] ?? null;
        } catch (\Throwable $th) {
            Log::error($th);
        }

        return null;
    }
}
