<?php

use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;
use App\Models\CorporateVehiclesQuotesRequest;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

function getRenewalQuote($enquiryId, $requestData, $productData)
{
    $mmv = get_mmv_details($productData, $requestData->version_id, 'sbi');
    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv
            ]
        ];
    }
    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'message' => 'Vehicle Not Mapped',
                'mmv' => $mmv
            ]
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'message' => 'Vehicle code does not exist with Insurance company',
                'mmv' => $mmv
            ]
        ];
    }
    CorporateVehiclesQuotesRequest::where('user_product_journey_id', $requestData->user_product_journey_id)->update([
        'frontend_tags' => NULL
    ]);
    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
    if ($premium_type == 'breakin') {
        $premium_type = 'comprehensive';
    }
    if ($premium_type == 'third_party_breakin') {
        $premium_type = 'third_party';
    }
    if ($premium_type == 'own_damage_breakin') {
        $premium_type = 'own_damage';
    }

    //car age calculation
    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
    $car_age = ceil($age / 12);

    //token generation 
    $data = cache()->remember('constants.IcConstants.sbi.SBI_END_POINT_URL_GET_TOKEN.CAR', 60 * 2.5, function () use ($enquiryId, $productData) {
        return getWsData(config('constants.IcConstants.sbi.SBI_END_POINT_URL_GET_TOKEN'), [], 'sbi', [
            'enquiryId' => $enquiryId,
            'requestMethod' => 'get',
            'productName'  => $productData->product_name,
            'company'  => 'sbi',
            'section' => $productData->product_sub_type_code,
            'method' => 'Generate Token',
            'transaction_type' => 'quote'
        ]);
    });
    if ($data['response']) {
        $token_data = json_decode($data['response'], TRUE);
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        //renewal getQuote service 
        $policy_data = [
            "renewalQuoteRequestHeader" => [
                "requestID" => $enquiryId,
                "action" => "renewalQuote",
                "channel" => "SBIGIC",
                "transactionTimestamp" => date('d-M-Y-H:i:s')
            ],
            "renewalQuoteRequestBody" => [
                "payload" => [
                    "policytype" => "Renewal",
                    "policyNumber" => $user_proposal['previous_policy_number'] ?? null, #demo policy number
                    "productCode" => "PMCAR001"
                ]
            ]
        ];
        $encrypt_req = [
            'data' => json_encode($policy_data),
            'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'development',
            'action' => 'encrypt'
        ];
        $encrpt_resp = httpRequest('sbi_encrypt', $encrypt_req, [], [], [], true, true)['response'];
        if (isset($encrpt_resp)) {

            $encrpt_policy_data['DecryptedGCM'] = trim($encrpt_resp);
            $get_response = getWsData(config('constants.IcConstants.sbi.SBI_RENEWAL_FETCH_END_POINT_URL'),  $encrpt_policy_data, 'sbi', [
                'section' => $productData->product_sub_type_code,
                'method' => 'Renewal Fetch Policy Details',
                'requestMethod' => 'post',
                'company'  => 'sbi',
                'enquiryId' => $enquiryId,
                'productName' => $productData->product_name,
                'transaction_type' => 'quote',
                'authorization' => $token_data['access_token'] ?? $token_data['accessToken'],
            ]);
            $data = $get_response['response'];
            $data = json_decode($get_response['response'], true);
            if (isset($data['EncryptedGCM'])) {
                $decrypt_req = [
                    'data' => $data['EncryptedGCM'],
                    'env' => (env('APP_ENV') != 'local') ? 'PROD' : 'development',
                    'action' => 'decrypt',
                    // 'file'  => 'true'
                ];
            }
            $decrpt_resp = httpRequest('sbi_encrypt', $decrypt_req, [], [], [], true, true)['response'];
            $flag = $decrpt_resp['renewalQuoteResponseBody']['payload']['flag'] ?? '';
            $flag_description = [
                '1' => 'Policy number/engine/chassis/registration number is null. Vaidation unsuccessful.',
                '2' => 'mdm id/engine/chassis/registration number is null. Vaidation unsuccessful.',
                '3' => 'Policy can be renewable.(i.e. effective date > =system date and <=30 dyas ( > =1 day <=30 days)). Vaidation successful',
                '4' => 'Data not available/Referred to UW/Renewal quote error. Vaidation unsuccessful.',
                '5' => 'Null data for the given policy/mdm id/engine/chassis/registration number details. Vaidation unsuccessful.',
                '6' => 'Policy number/mdm id/engine/chassis/registration number details are invalid.Vaidation unsuccessful.',
                '7' => 'sql exception/query error. Vaidation/class file execution unsuccessful',
                '8' => 'future dated policies. (i.e. effective date > system date( > 30 days))',
                '9' => 'Back dated policies. (i.e. effective date < = system date( < 1 day))',
                '10' => 'Pending endorsement (maker/checker/UW)',
                '11' => 'Policy already renewed',
                '12' => 'Renewal notice not available',
                '13' => 'Pending claim',
                '14' => 'Total loss claim',
                '15' => 'Rejected',
                '16' => 'Multiple policy record',
            ];
            if ($flag === "3") {
                $idv =  $decrpt_resp['renewalQuoteResponseBody']['payload']['sumInsured'];
                $product_name =  $decrpt_resp['renewalQuoteResponseBody']['payload']['productName'];
                $end_date =  $decrpt_resp['renewalQuoteResponseBody']['payload']['renewalDueDate'];
                $renewal_quote_number =  $decrpt_resp['renewalQuoteResponseBody']['payload']['renewalQuoteNumber'];
                $start_date =  $decrpt_resp['renewalQuoteResponseBody']['payload']['startTime'];
                $net_premium =  $decrpt_resp['renewalQuoteResponseBody']['payload']['annualGrossPremium'];
                $final_amount =  $decrpt_resp['renewalQuoteResponseBody']['payload']['renewalPremiumAmount'];
                $prev_policy_number =  $decrpt_resp['renewalQuoteResponseBody']['payload']['previousPolicyNo'];
                $data_response = [
                    // 'table' => ['table'],
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => true,
                    'msg' => 'Found',
                    'Data' => [
                        'flag' => $flag,
                        'isRenewal' => 'Y',
                        'idv' => $idv,
                        'min_idv' => $idv,
                        'max_idv' => $idv,
                        'default_idv' => $idv,
                        'vehicle_idv' => $idv,
                        'qdata' => null,
                        'pp_enddate' => $requestData->previous_policy_expiry_date,
                        'addonCover' => null,
                        'addon_cover_data_get' => '',
                        'rto_decline' => null,
                        'rto_decline_number' => null,
                        'mmv_decline' => null,
                        'mmv_decline_name' => null,
                        'policy_type' => ($premium_type == 'third_party' ? 'Third Party' : ($premium_type == 'own_damage' ? 'Own Damage' : 'Comprehensive')),
                        'cover_type' => '1YC',
                        'hypothecation' => '',
                        'hypothecation_name' => '',
                        'vehicle_registration_no' => $requestData->rto_code,
                        'voluntary_excess' => null,
                        'version_id' => $mmv_data->ic_version_code,
                        'selected_addon' => [],
                        'showroom_price' => $idv,
                        'fuel_type' => $mmv_data->fuel_type,
                        'ncb_discount' => $requestData->applicable_ncb,
                        'company_name' => $productData->company_name,
                        'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                        'product_name' => $product_name,
                        'mmv_detail' => [
                            'manf_name'             => $mmv_data->vehicle_manufacturer,
                            'model_name'            => $mmv_data->vehicle_model_name,
                            'version_name'          => $mmv_data->variant,
                            'fuel_type'             => $mmv_data->fuel_type,
                            'seating_capacity'      => $mmv_data->seating_capacity,
                            'carrying_capacity'     => $mmv_data->carrying_capacity,
                            'cubic_capacity'        => $mmv_data->cubic_capacity,
                            'gross_vehicle_weight'  => 1, //$mmv_data->gross_weight ?? 1,
                            'vehicle_type'          => '', //$mmv_data->vehicle_class_desc,
                        ],
                        'vehicle_register_date' => $requestData->vehicle_register_date,
                        'master_policy_id' => [
                            'policy_id' => $productData->policy_id,
                            'policy_no' => $prev_policy_number,
                            'policy_start_date' => date('d-m-Y', strtotime($start_date)),
                            'policy_end_date' => date('d-m-Y', strtotime($end_date)),
                            'sum_insured' => $productData->sum_insured,
                            'corp_client_id' => $productData->corp_client_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'insurance_company_id' => $productData->company_id,
                            'status' => $productData->status,
                            'corp_name' => "Ola Cab",
                            'company_name' => $productData->company_name,
                            'logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                            'product_sub_type_name' => $productData->product_sub_type_name,
                            'flat_discount' => $productData->default_discount,
                            'predefine_series' => "",
                            'is_premium_online' => $productData->is_premium_online,
                            'is_proposal_online' => $productData->is_proposal_online,
                            'is_payment_online' => $productData->is_payment_online
                        ],
                        'motor_manf_date' => $requestData->manufacture_year,
                        'vehicleDiscountValues' => [
                            'master_policy_id' => $productData->policy_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'segment_id' => 0,
                            'rto_cluster_id' => 0,
                            'car_age' => $car_age,
                            'ic_vehicle_discount' => null,
                        ],
                        'ic_vehicle_discount' => null,
                        'basic_premium' => $net_premium,
                        'deduction_of_ncb' => null,
                        'tppd_premium_amount' => null,
                        'tppd_discount' => null,
                        'motor_electric_accessories_value' => null,
                        'motor_non_electric_accessories_value' => null,
                        'motor_lpg_cng_kit_value' => null,
                        'cover_unnamed_passenger_value' => isset($cover_pa_unnamed_passenger_premium) ? $cover_pa_unnamed_passenger_premium : 0,
                        'multi_year_cover_unnamed_passenger_value' => isset($cover_pa_unnamed_passenger_premium) ? $cover_pa_unnamed_passenger_premium * 3 : 0,
                        'seating_capacity' => null,
                        'default_paid_driver' => null,
                        'motor_additional_paid_driver' => null,
                        'multi_year_motor_additional_paid_driver' => null,
                        'GeogExtension_ODPremium'                     => null,
                        'GeogExtension_TPPremium'                     => null,
                        'compulsory_pa_own_driver' => null,
                        'total_accessories_amount(net_od_premium)' => 0,
                        'total_own_damage' => null,
                        'cng_lpg_tp' => null,
                        'total_liability_premium' => null,

                        'net_premium' => $net_premium ?? 0,
                        'service_tax_amount' => 0,
                        'service_tax' => 0,
                        'total_discount_od' => 0,
                        'add_on_premium_total' => 0,
                        'addon_premium' => 0,
                        'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                        'quotation_no' => $renewal_quote_number,
                        'premium_amount'  => null,
                        'antitheft_discount' => null,
                        'final_od_premium' => null,
                        'final_tp_premium' => null,
                        'final_total_discount' => null,
                        'final_net_premium' => $final_amount,
                        'final_gst_amount' => null,
                        'final_payable_amount' => $final_amount,
                        'service_data_responseerr_msg' => 'success',
                        'user_id' => $requestData->user_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'user_product_journey_id' => $requestData->user_product_journey_id,
                        'business_type' => ($requestData->business_type == 'newbusiness') ? 'New Business' : ((($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party') || $requestData->previous_policy_type == 'Not sure') ? 'Break-in' : $requestData->business_type),
                        'service_err_code' => NULL,
                        'service_err_msg' => NULL,
                        'policyStartDate' => date('d-m-Y', strtotime($start_date)),
                        'policyEndDate' => date('d-m-Y', strtotime($end_date)),
                        'ic_of' => $productData->company_id,
                        'vehicle_in_90_days' => NULL,
                        'get_policy_expiry_date' => NULL,
                        'get_changed_discount_quoteid' => 0,
                        'vehicle_discount_detail' => [
                            'discount_id' => NULL,
                            'discount_rate' => NULL
                        ],
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online,
                        'policy_id' => $productData->policy_id,
                        'insurane_company_id' => $productData->company_id,
                        "max_addons_selection" => NULL,
                        'add_ons_data' => [
                            'in_built'   => [],
                            'additional' => [],
                        ],
                        'applicable_addons' => [],
                        'no_calculation' => 'Y'
                    ]
                ];
                UserProposal::where('user_product_journey_id' , $enquiryId)
                ->update([
                    'proposal_no' => $renewal_quote_number,
                ]);
                return camelCase($data_response);
            } else {
                return [
                    'status' => false,
                    'flag' => $flag,
                    'message' => ($flag_description[$flag]) ?? 'Insurer not reachable'
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Error in Encrytion'
            ];
        }
    } else {
        return [
            'webservice_id' => $data['webservice_id'],
            'table' => $data['table'],
            'status' => false,
            'message' => 'Token Generation Issue'
        ];
    }
}
