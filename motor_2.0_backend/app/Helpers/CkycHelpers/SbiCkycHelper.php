<?php

    use App\Models\UserProposal;
    use App\Http\Controllers\CkycController;
    use Illuminate\Http\Request;

    function ckycVerifications(UserProposal $proposal)
    {
        $ckycController = new CkycController;

        if ($proposal->proposer_ckyc_details?->is_document_upload == 'Y') {
            return ckycVerificationStep2($ckycController, $proposal);
        } else {
            return ckycVerificationStep1($ckycController, $proposal);
        }
    }

    function ckycVerificationStep1(CkycController $ckycController, UserProposal $proposal)
    {
        $ckyc_verification_types = [
            'ckyc_number' => 'ckyc_number',
            'pan_card' => 'pan_number_with_dob'
        ];

        $ckyc_verification_first = $ckycController->ckycVerifications(new Request([
            'companyAlias' => 'sbi',
            'enquiryId' => customEncrypt($proposal->user_product_journey_id),
            'mode' => $ckyc_verification_types[$proposal->ckyc_type]
        ]));

        if ($ckyc_verification_first->status() == 200) {
            $ckyc_verification_first_response = $ckyc_verification_first->getOriginalContent();

            if ($ckyc_verification_first_response['status']) {
                return saveCkycDetails($ckyc_verification_first_response['data'], $ckycController, $proposal);
            } else {
                $error_message = $ckyc_verification_first_response['data']['message'] ?? 'CKYC verification failed';

                if ($proposal->ckyc_type == 'ckyc_number') {
                    $error_message = 'CKYC verification failed using CKYC number. Please check the entered CKYC Number or try with another method';
                }

                return [
                    'status' => false,
                    'mode' => $ckyc_verification_types[$proposal->ckyc_type],
                    'message' => $error_message,
                    'msg' => $error_message
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Unable to verify CKYC, please try again after some time.'
            ];
        }
    }

    function ckycVerificationStep2(CkycController $ckycController, UserProposal $proposal)
    {
        $ckyc_verification_second = $ckycController->ckycVerifications(new Request([
            'companyAlias' => 'sbi',
            'enquiryId' => customEncrypt($proposal->user_product_journey_id),
            'mode' => 'documents'
        ]));

        if ($ckyc_verification_second->status() == 200) {
            $ckyc_verification_second_response = $ckyc_verification_second->getOriginalContent();

            if ($ckyc_verification_second_response['status']) {
                return [
                    'status' => true
                ];
            } else {
                if($ckyc_verification_second_response['data']['file_upload'] ?? false) {
                    $ckyc_meta_data = json_decode($proposal->ckyc_meta_data ?? '', 1);
                    $ckyc_meta_data['file_upload'] = $ckyc_verification_second_response['data']['meta_data']['file_upload'];

                    $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->first();

                    if (!empty($updateProposal)) {
                        $updateProposal->update([
                            'ckyc_meta_data' => json_encode($ckyc_meta_data)
                        ]);
                    }
                }
                return [
                    'status' => false,
                    'message' => $ckyc_verification_second_response['data']['message'] ?? 'CKYC verification failed',
                    'msg' => $ckyc_verification_second_response['data']['message'] ?? 'CKYC verification failed'
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Unable to upload documents, please try again after some time.'
            ];
        }
    }

    function saveCkycDetails($ckyc_response, CkycController $ckycController, UserProposal $proposal)
    {
        $ckyc_verification_first_response['response']['data']['customer_details'] = [
            'name' => $ckyc_response['customer_details']['fullName'] ?? null,
            'mobile' => $ckyc_response['customer_details']['mobileNumber'] ?? null,
            'dob' => $ckyc_response['customer_details']['dob'] ?? null,
            'address' => $ckyc_response['customer_details']['address'] ?? null,
            'pincode' => $ckyc_response['customer_details']['pincode'] ?? null,
            'email' => $ckyc_response['customer_details']['email'] ?? null,
            'pan_no' => $ckyc_response['customer_details']['panNumber'] ?? null,
            'ckyc' => $ckyc_response['data']['customer_details']['ckycNumber'] ?? null
        ];

        $ckyc_verification_first_response['response']['data']['customer_details']['address'] = implode(' ', array_filter([$ckyc_response['customer_details']['addressLine1'] ?? null, $ckyc_response['customer_details']['addressLine2'] ?? null, $ckyc_response['customer_details']['addressLine3'] ?? null]));

        $updated_proposal = $ckycController->saveCkycResponseInProposal(new Request([
            'company_alias' => 'sbi',
            'trace_id' => customEncrypt($proposal->user_product_journey_id)
        ]), $ckyc_verification_first_response, $proposal);

        foreach ($updated_proposal as $key => $u) {
            $proposal->$key = $u;
        }

        $proposal->save();

        return [
            'status' => true
        ];
    }