<?php

    use App\Models\UserProposal;
    use Illuminate\Http\Request;
    use App\Http\Controllers\CkycController;

    function ckycVerifications(UserProposal $proposal, $product_data = [])
    {
        $ckycController = new CkycController;

        if ($proposal->proposer_ckyc_details?->is_document_upload == 'Y') {
            if (config('BAJAJ_OCR_FLOW') == 'Y') {
                if ($product_data['is_premium_different']) {
                    if (($proposal->quote_log->final_premium_amount <= 50000 && $proposal->corporate_vehicles_quotes_request?->vehicle_owner_type == 'I') == false || $proposal->corporate_vehicles_quotes_request?->vehicle_owner_type == 'C') {
                        $ckyc_step_2 = ckycVerificationForDifferentPremium($ckycController, $proposal, $product_data);

                        if ($ckyc_step_2['status']) {
                            return $ckyc_step_2;
                        }
                    } else if (($proposal->quote_log->final_premium_amount <= 50000 && $proposal->corporate_vehicles_quotes_request?->vehicle_owner_type == 'I') && $product_data['trigger_old_document_flow'] == 'Y') {
                        $ckyc_step_2 = ckycVerificationForDifferentPremium($ckycController, $proposal, $product_data);

                        if ($ckyc_step_2['status']) {
                            return $ckyc_step_2;
                        }
                    }
                }
            }else{
                if ($product_data['is_premium_different']) {
                    $ckyc_step_2 = ckycVerificationForDifferentPremium($ckycController, $proposal, $product_data);

                    if ($ckyc_step_2['status']) {
                        return $ckyc_step_2;
                    }
                }
            }

            return ckycVerificationStep4($ckycController, $proposal, $product_data);
        } else {
            if ($proposal->ckyc_type == 'ckyc_number') {
                return ckycVerificationStep1($ckycController, $proposal, $product_data);
            } elseif ($proposal->ckyc_type == 'pan_card') {
                return ckycVerificationStep2($ckycController, $proposal, $product_data);
            } else {
                return ckycVerificationForDifferentPremium($ckycController, $proposal, $product_data);
            }
        }
    }

    function ckycVerificationStep1(CkycController $ckycController, UserProposal $proposal, $product_data)
    {
        // verify CKYC using CKYC number
        $ckyc_verification_first = $ckycController->ckycVerifications(new Request([
            'companyAlias' => 'bajaj_allianz',
            'mode' =>  'ckyc_number',
            'enquiryId' => customEncrypt($proposal->user_product_journey_id),
            'user_id'   => $product_data['user_id'],
            'product_code' => $product_data['product_code']
        ]));

        if ($ckyc_verification_first->status() == 200) {
            $ckyc_verification_first_response = $ckyc_verification_first->getOriginalContent();

            if ($ckyc_verification_first_response['status']) {
                return saveCkycDetails($ckyc_verification_first_response['data'], $proposal);
            } else {
                if (!empty($proposal->pan_number)) {
                    return ckycVerificationStep2($ckycController, $proposal, $product_data); 
                }
                return [
                    'status' => false,
                    'msg' => 'CKYC verification failed using CKYC number. Please check the entered CKYC Number or try with other method',
                    'message' => 'Please try with PAN number',
                    'poi_status' => null,
                    'poa_status' => null
                ];
            }
        } else {
            return [
                'status' => false,
                'msg' => 'An error occurred while verifying CKYC - step 1',
            ];
        }
    }

    function ckycVerificationStep2(CkycController $ckycController, UserProposal $proposal, $product_data)
    {
        // verify CKYC using PAN number
        $ckyc_verification_second = $ckycController->ckycVerifications(new Request([
            'companyAlias' => 'bajaj_allianz',
            'mode' =>  'pan_number_with_dob',
            'enquiryId' => customEncrypt($proposal->user_product_journey_id),
            'user_id'   => $product_data['user_id'],
            'product_code' => $product_data['product_code']
        ]));

        if ($ckyc_verification_second->status() == 200) {
            $ckyc_verification_second_response = $ckyc_verification_second->getOriginalContent();

            if ($ckyc_verification_second_response['status']) {
                return saveCkycDetails($ckyc_verification_second_response['data'], $proposal);
            } else {
                if ((isset($ckyc_verification_second_response['data']['message']) && $ckyc_verification_second_response['data']['message'] == 'POI failed') || (empty($ckyc_verification_second_response['data']['meta_data']['poiStatus']) || in_array(($ckyc_verification_second_response['data']['meta_data']['poiStatus'] ?? ''), ['NOT_FOUND', 'NA', 'INVALID']))) {
                    return [
                        'status' => false,
                        'msg' => 'CKYC verification failed, please try using another ID.',
                        'message' => 'Please enter valid PAN number',
                        'poi_status' => false,
                        'data' => [
                            'poi_status' => false
                        ],
                        'poa_status' => null
                    ];
                } else {
                    return [
                        'status' => false,
                        'msg' => 'CKYC verification failed, please try using another ID.',
                        'message' => 'PAN number is valid. Please try with other id',
                        'poi_status' => true,
                        'data' => [
                            'poi_status' => true
                        ],
                        'poa_status' => null
                    ];
                }
            }
        } else {
            return [
                'status' => false,
                'msg' => 'An error occurred while verifying CKYC - step 2',
            ];
        }
    }

    function ckycVerificationStep3(CkycController $ckycController, UserProposal $proposal, $product_data)
    {
        // CKYC not verified using PAN number, but poi_status is FOUND, hence verify CKYC using other Id
        $ckyc_modes = [
            'aadhar_card' => 'aadhar',
            'passport' => 'passport',
            'voter_id' => 'voter_card',
            'driving_license' => 'driving_licence',
            'gst_number' => 'gst_number',
            'gstNumber' => 'gst_number',            
        ];

        $ckyc_verification_third = $ckycController->ckycVerifications(new Request([
            'companyAlias' => 'bajaj_allianz',
            'mode' =>  $ckyc_modes[$proposal->ckyc_type],
            'enquiryId' => customEncrypt($proposal->user_product_journey_id),
            'user_id'   => $product_data['user_id'],
            'product_code' => $product_data['product_code']
        ]));

        if ($ckyc_verification_third->status() == 200) {
            $ckyc_verification_third_response = $ckyc_verification_third->getOriginalContent();

            if ($ckyc_verification_third_response['status']) {
                return saveCkycDetails($ckyc_verification_third_response['data'], $proposal);
            } else {
                return [
                    'status' => false,
                    'msg' => 'CKYC verification failed. Try other method',
                    'message' => 'Please provide documents',
                    'poi_status' => true,
                    'data' => [
                        'poi_status' => true
                    ],
                    'poa_status' => false
                ];
            }
        } else {
            return [
                'status' => false,
                'msg' => 'An error occurred while verifying CKYC - step 3',
            ];
        }
    }

    function ckycVerificationStep4(CkycController $ckycController, UserProposal $proposal, $product_data)
    {
        // CKYC not verified using PAN number, but got poi_status as FOUND. Then tried to verify using other ids, but got poa_status as NOT_FOUND/NA, hence upload documents
        $ckyc_verification_fourth = $ckycController->ckycUploadDocuments(new Request([
            'companyAlias' => 'bajaj_allianz',
            'mode' =>  'documents',
            'enquiryId' => customEncrypt($proposal->user_product_journey_id),
            'user_id'   => $product_data['user_id'],
            'product_code' => $product_data['product_code'],
            'trigger_old_document_flow' => $product_data['trigger_old_document_flow']
        ]));

        if ($ckyc_verification_fourth->status() == 200) {
            $ckyc_verification_fourth_response = $ckyc_verification_fourth->getOriginalContent();

            if ($ckyc_verification_fourth_response['status']) {
                return [
                    'status' => true
                ];
            } else {
                return [
                    'status' => false,
                    'msg' => $ckyc_verification_fourth_response['data']['message'] ?? 'An error occurred while uploading documents',
                    'message' => $ckyc_verification_fourth_response['data']['message'] ?? $ckyc_verification_fourth_response['message'] ?? 'An error occurred while uploading documents'
                ];
            }
        } else {
            return [
                'status' => false,
                'msg' => 'An error occurred while verifying CKYC - step 4',
            ];
        }
    }

    function ckycVerificationForDifferentPremium(CkycController $ckycController, UserProposal $proposal, $product_data)
    {
        $ckyc_step_2 = ckycVerificationStep2($ckycController, $proposal, $product_data);

        if ( ! $ckyc_step_2['status'] && $ckyc_step_2['poi_status'] && !empty($proposal->ckyc_type) && !in_array($proposal->ckyc_type, ['pan_card', 'ckyc_number'])) {
            return ckycVerificationStep3($ckycController, $proposal, $product_data);
        }

        return $ckyc_step_2;
    }

    function saveCkycDetails($ckyc_details, UserProposal $proposal)
    {
        if ( ! empty($ckyc_details['customer_details'])) {
            $salutations = array("mr.", "ms.", "mrs.", "miss.", "mr", "ms", "mrs", "miss");

            $name_arr = explode(' ', $ckyc_details['customer_details']['fullName'] ?? null);

            if (in_array(strtolower($name_arr[0]), $salutations) || empty($name_arr[0])) {
                array_shift($name_arr);

                $name_arr = array_values($name_arr);
            }

            if ( ! empty($name_arr)) {
                $additional_details = json_decode($proposal->additional_details, true);

                if ($proposal->corporate_vehicles_quotes_request?->vehicle_owner_type == 'I' && count($name_arr) > 1) {
                    $last_name = $name_arr[count($name_arr) - 1];
    
                    array_pop($name_arr);
                }

                $proposal->first_name = implode(' ', $name_arr);
                $proposal->last_name = $last_name ?? null;

                $additional_details['owner']['firstName'] = $proposal->first_name;
                $additional_details['owner']['lastName'] = $last_name ?? null;

                $proposal->additional_details = json_encode($additional_details, true);

                $proposal->save();
            }
        }

        return [
            'status' => true
        ];
    }

    function isNewPremiumToBeStored($new_premium, UserProposal $proposal)
    {
        if ( ! empty($proposal->proposer_ckyc_details?->meta_data)) {
            $proposer_ckyc_meta_data = json_decode($proposal->proposer_ckyc_details?->meta_data, true);
        }

        $is_premium_to_be_stored = $new_premium != $proposal->final_payable_amount || empty($proposer_ckyc_meta_data['last_premium_calculation_timestamp']) || ( ! empty($proposer_ckyc_meta_data['last_premium_calculation_timestamp']) && $proposer_ckyc_meta_data['last_premium_calculation_timestamp'] < strtotime(date('Y-m-d')));

        /* return config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IcConstants.bajaj_allianz.IS_NEW_FLOW_ENABLED_FOR_BAJAJ_ALLIANZ_CKYC') == 'Y' && $is_premium_to_be_stored; */
        return config('constants.IS_CKYC_ENABLED') != 'Y' || (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IcConstants.bajaj_allianz.IS_NEW_FLOW_ENABLED_FOR_BAJAJ_ALLIANZ_CKYC') != 'Y') || (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IcConstants.bajaj_allianz.IS_NEW_FLOW_ENABLED_FOR_BAJAJ_ALLIANZ_CKYC') == 'Y' && $is_premium_to_be_stored);
    }