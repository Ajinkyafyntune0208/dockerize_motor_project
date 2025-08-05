<?php

use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Http\Controllers\CkycController;


function ckycVerifications($ckyc_inputs)
{
    $ckycController = new CkycController;

    extract($ckyc_inputs);

    $additional_details = compact('proposalSubmitResponse', 'webserviceData', 'is_breakin_case');

    $additionalDetails = json_decode($proposal->additional_details, 1);

    if(($additionalDetails['owner']['formType'] ?? '') == 'form60') {
        $additional_details['is_form60'] = true;
        $form60Response = ckycVerificationStep1($ckycController, $proposal, $additional_details);
        $ckyc_response = $form60Response;
        if($form60Response['data']['verification_status'] || $form60Response['message'] == 'OTP Sent Successfully!') {
            return getResponse(compact('ckyc_response', 'webserviceData', 'proposal', 'proposalSubmitResponse', 'is_breakin_case'));
        } else if($form60Response['message'] == 'OTP Sent Successfully!') {
            return getResponse(compact('ckyc_response', 'webserviceData', 'proposal', 'proposalSubmitResponse', 'is_breakin_case'));
        } else if ($proposal->proposer_ckyc_details?->is_document_upload == 'Y') {
            return ckycVerificationStep2($ckycController, $proposal, $additional_details);
        } else {
            return getResponse(compact('ckyc_response', 'webserviceData', 'proposal', 'proposalSubmitResponse', 'is_breakin_case'));
        }
    }
    if ($proposal->proposer_ckyc_details?->is_document_upload == 'Y') {
        $ckycVerificationStep1 = ckycVerificationStep1($ckycController, $proposal, $additional_details);

        if ($ckycVerificationStep1['data']['verification_status']) {
            return $ckycVerificationStep1;
        }

        return ckycVerificationStep2($ckycController, $proposal, $additional_details);
    } else {
        return ckycVerificationStep1($ckycController, $proposal, $additional_details);
    }
}

function ckycVerificationStep1(CkycController $ckycController, UserProposal $proposal, $additional_details = [])
{
    extract($additional_details);

    $ckyc_verification_step_1 = $ckycController->ckycVerifications(new Request([
        "companyAlias" => "tata_aig",
        "enquiryId" => customEncrypt($proposal->user_product_journey_id),
        "mode" => 'pan_number',
        'is_form60' => ($additional_details['is_form60'] ?? false)
    ]));

    if ($ckyc_verification_step_1->status() == 200) {
        $ckyc_response = $ckyc_verification_step_1->getOriginalContent();

        if (($proposal->proposer_ckyc_details?->is_document_upload == 'Y' || ($additional_details['is_form60'] ?? false)) && ! $ckyc_response['data']['verification_status']) {
            return $ckyc_response;
        }

        $response = getResponse(compact('ckyc_response', 'webserviceData', 'proposal', 'proposalSubmitResponse', 'is_breakin_case'));

        $additionalDetails = json_decode($proposal->additional_details, true) ?? [];

        if (
            ($proposal->ckyc_type == 'cinNumber' || ($additionalDetails['owner']['isCinPresent'] ?? false) == 'NO') &&
            ($response['msg'] ?? '') == 'CKYC completed. Please try with CKYC/CIN number'
        ) {
            return ckycVerificationStep3($ckycController, $proposal, $additional_details);
        }
        return $response;
    } else {
        return [
            'status' => false,
            'ckyc_status' => false,
            'msg' => 'Unable to verify CKYC, please try again after some time. - Step 1'
        ];
    }
}

function ckycVerificationStep2(CkycController $ckycController, UserProposal $proposal, $additional_details)
{
    extract($additional_details);

    $ckyc_verification_step_2 = $ckycController->ckycVerifications(new Request([
        'companyAlias' => 'tata_aig',
        'enquiryId' => customEncrypt($proposal->user_product_journey_id),
        'mode' => 'documents'
    ]));

    if ($ckyc_verification_step_2->status() == 200) {
        $ckyc_response = $ckyc_verification_step_2->getOriginalContent();

        return getResponse(compact('ckyc_response', 'webserviceData', 'proposal', 'proposalSubmitResponse', 'is_breakin_case'));
    } else {
        return [
            'status' => false,
            'ckyc_status' => false,
            'msg' => 'Unable to verify CKYC, please try again after some time. - Step 2'
        ];
    }
}

function getResponse($additional_details)
{
    extract($additional_details);

    if ($ckyc_response['data']['verification_status'] ?? false == true) {
        return [
            'status' => true,
            'ckyc_status' => true,
            'msg' => 'Proposal Submited Successfully..!',
            'webservice_id' => isset($webserviceData->webservice_id) ? $webserviceData->webservice_id : $webserviceData['webservice_id'],
            'table' => isset($webserviceData->table) ? $webserviceData->table : $webserviceData['table'],
            'data' => [
                'verification_status' => true,
                'proposalId' => $proposal->user_proposal_id,
                'userProductJourneyId' => $proposal->user_product_journey_id,
                'proposalNo' => $proposalSubmitResponse->proposalSubmit->proposal_no ?? $proposalSubmitResponse->tata_aig_v2->proposal_no ?? $proposalSubmitResponse['proposal_no'] ?? $proposalSubmitResponse['proposalno'],
                'finalPayableAmount' => $proposal->final_payable_amount,
                'is_breakin' => $is_breakin_case ?? null,
                'isBreakinCase' => $is_breakin_case ?? null,
               'inspection_number' => $proposalSubmitResponse->proposalSubmit->ticket_number ?? $proposalSubmitResponse['ticket_number'] ?? '',
                'kyc_verified_using' => $ckyc_response['ckyc_verified_using'] ?? null,
                'kyc_status' => true
            ]
        ];
    } else {
        if ( ! empty($ckyc_response['data']['otp_id'] ?? '')) {
            return [
                "status" => true,
                "message" => "OTP Sent Successfully!",
                "data" => [
                    "verification_status" => false,
                    "message" => "OTP Sent Successfully!",
                    'otp_id' => $ckyc_response['data']['otp_id'],
                    'is_breakin' => $is_breakin_case ?? null,
                    'isBreakinCase' => $is_breakin_case ?? null,
                    'kyc_status' => false
                ]
            ];
        }

        if ($ckyc_response['data']['meta_data']['is_doc_upload_needed'] ?? false) {
            return [
                'status' => false,
                'ckyc_status' => false,
                'msg' => 'CKYC verification failed. Try other method'
            ];
        }

        if ($proposal->ckyc_type == 'pan_card' && $ckyc_response['data']['message'] ?? '' == 'CKYC not completed. Please retry with another id') {
            $ckyc_response['data']['message'] = 'Try With CIN Number in Case of Company';
        }

        return [
            'status' => false,
            'ckyc_status' => false,
            'msg' => $ckyc_response['data']['message'] ?? 'Something went wrong while doing the CKYC. Please try again.'
        ];
    }
}

function updateCkycDetails(UserProposal $proposal)
{
    $ckyc_doc_data = json_decode($proposal->ckyc_upload_documents->cky_doc_data, true);
    $ckyc_type = '';
    $ckyc_type_value = '';

    switch ($ckyc_doc_data['proof_of_address']['poa_identity']) {
        case 'aadharNumber':
            $ckyc_type = 'AADHAAR';
            $ckyc_type_value = $ckyc_doc_data['proof_of_address']['poa_aadharNumber'];
            break;

        case 'voterId':
            $ckyc_type = 'VOTERID';
            $ckyc_type_value = $ckyc_doc_data['proof_of_address']['poa_voterId'];
            break;

        case 'drivingLicense':
            $ckyc_type = 'DL';
            $ckyc_type_value = $ckyc_doc_data['proof_of_address']['poa_drivingLicense'];
            break;

        case 'passportNumber':
            $ckyc_type = 'PASSPORT';
            $ckyc_type_value = $ckyc_doc_data['proof_of_address']['poa_passportNumber'];
            break;
        
        default:
            return [
                'status' => false,
                'msg' => 'POA details required'
            ];
            break;
    }

    $product_sub_type_code = $proposal->quote_log->master_policy->product_sub_type_code->product_sub_type_code;
   
    include_once app_path() . '/Helpers/' . (in_array(get_parent_code($proposal->quote_log->master_policy->product_sub_type_code->product_sub_type_id), ['PCV', 'GCV']) ? 'Cv' : ucfirst(strtolower($product_sub_type_code))) . 'WebServiceHelper.php';

    if (ucfirst(strtolower($product_sub_type_code)) == 'Car') {
        $product_code = config('constants.IcConstants.tata_aig.PRODUCT_ID');
    } elseif (ucfirst(strtolower($product_sub_type_code)) == 'Bike') {
        $product_code = config('constants.IcConstants.tata_aig.bike.PRODUCT_CODE');
    } else {
        $product_code = config("constants.IcConstants.tata_aig.cv.PRODUCT_ID");
    }

    $update_request = [
        'T' => config('constants.IcConstants.tata_aig.TOKEN'),
        'product_code' => $product_code,
        'quote_id' => $proposal->unique_proposal_id,
        'proposal_no' => $proposal->proposal_no,
        'p_ckyc_pan' => $proposal->pan_number,
        'p_ckyc_no' => '',
        'p_ckyc_id_type' => $ckyc_type,
        'p_ckyc_id_no' => $ckyc_type_value,
        'timestamp' => date('Y-m-d\TH:i:sP'),
        'kyc_status' => 'SUCCESS',
        'kyc_flow' => 'API'
    ];

    $response = getWsData(config('constants.IcConstants.tata_aig.TATA_AIG_UPDATE_CKYC_DETAILS'), $update_request, 'tata_aig', [
        'enquiryId' => $proposal->user_product_journey_id,
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ],
        'requestMethod' => 'post',
        'requestType' => 'json',
        'section' => $proposal->quote_log->master_policy->product_sub_type_code->product_sub_type_code,
        'method' => 'Update CKYC Details',
        'transaction_type' => 'proposal',
        'productName' => $proposal->quote_log->master_policy->master_product->product_name,
    ]);

    if ( ! empty($response['response'])) {
        $response_data = json_decode($response['response'], true);

        if (($response_data['errcode'] ?? '') == 'KYC002') {
            return [
                'status' => true
            ];
        }
    }

    return [
        'status' => false,
        'msg' => $response_data['message'] ?? 'An error occurred while updating CKYC details'
    ];
}

function ckycVerificationStep3(CkycController $ckycController, UserProposal $proposal, $additional_details)
{
    //This is cin verification in case of corporate case
    
    extract($additional_details);

    $response = $ckycController->ckycVerifications(new Request([
        "companyAlias" => "tata_aig",
        "enquiryId" => customEncrypt($proposal->user_product_journey_id),
        "mode" => 'cin_number',
        'is_form60' => ($additional_details['is_form60'] ?? false)
    ]));

    if ($response->status() == 200) {
        $ckyc_response = $response->getOriginalContent();

        return getResponse(compact('ckyc_response', 'webserviceData', 'proposal', 'proposalSubmitResponse', 'is_breakin_case'));
    } else {
        return [
            'status' => false,
            'ckyc_status' => false,
            'msg' => 'Unable to verify CKYC, please try again after some time. - Step 3'
        ];
    }

}
