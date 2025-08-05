<?php

use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use Spatie\ArrayToXml\ArrayToXml;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Storage;


function paymentstatuscheck($policyDetails, $proposal,$rehit = false)
{
    $paymentRequestResponse = PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
    ->orderBy('active', 'desc')
        ->get();

    $userJourneyData = UserProductJourney::find($proposal->user_product_journey_id);
    $productParent = strtoupper(get_parent_code($userJourneyData->product_sub_type_id));

    $policyNumber = null;
    $policyURL = null;
    $status = false;

    $previousProposalNo = $previousQuoteId = null;

    foreach ($paymentRequestResponse as $value) {
        $quoteXmlData = $value->xml_data;
        if (empty($quoteXmlData)) {
            continue;
        }
        $quoteXmlDataJson = json_decode($quoteXmlData);
        
        $proposalNo = $value->order_id;
        $quoteId = $quoteXmlDataJson?->form_data?->QuoteId;
        if (empty($quoteId) || empty($proposalNo)) {
            continue;
        }

        if ($previousProposalNo == $proposalNo && $previousQuoteId == $quoteId) {
            continue;
        }

        $previousProposalNo = $proposalNo;
        $previousQuoteId = $proposalNo;

        $apiRequest = [
            'ProposalNo' => $proposalNo,
            'QuoteID' => $quoteId
        ];

        $getResponse = getWsData(
            config('constants.IcConstants.shriram.SHRIRAM_PAYMENT_STATUS_CHECK_URL'),
            $apiRequest,
            'shriram',
            [
                'enquiryId' => $proposal->user_product_journey_id,
                'headers' => [
                    'Username' => config('constants.IcConstants.shriram.SHRIRAM_USERNAME'),
                    'Password' => config('constants.IcConstants.shriram.SHRIRAM_PASSWORD'),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'requestMethod' => 'post',
                'requestType' => 'json',
                'section' => $productParent,
                'method' => 'Payment Status Check',
                'transaction_type' => 'proposal',
            ]
        );
        $rehitPaymentApiResponse = $getResponse['response'];

        if (empty($rehitPaymentApiResponse)) {
            continue;
        }
        $rehitPaymentApiRes = json_decode($rehitPaymentApiResponse, true);

        foreach ($rehitPaymentApiRes['Response'] as $res) {
            if (!empty($res['TransactionStatus']) && $res['TransactionStatus'] == 'Success') {
                $status = true;
                $policyNumber = $res['PolicyNo'] ?? null;
                $policyURL = $res['PolicyURL'] ?? null;

                $value->update([
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                    'active' => 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                break;
            }
        }

        if ($status) {
            break;
        }
    }

    if ($status) {

        updateJourneyStage([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'stage' => STAGE_NAMES['PAYMENT_SUCCESS'],
        ]);

        if (!empty($policyURL) || !empty($policyNumber)) {
            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
            ]);
            PolicyDetails::updateOrCreate(
                ['proposal_id' => $proposal->user_proposal_id],
                [
                    'ic_pdf_url'     => $policyURL,
                    'policy_number' =>  $policyNumber
                ]
            );

            if (!empty($policyURL)) {
                return downloadPDFFromURL($policyNumber, $policyURL, $proposal, $productParent);
            } elseif (!empty($policyNumber)) {
                return policyPDFJSON($proposal, $policyNumber);
            }
        }

        return [
            'status' => true,
            'message' => 'Policy Number not found',
        ];
    }

    return [
        'status' => false,
        'message' => 'Payment is pending'
    ];
}

function policyPDFJSON($proposal, $policy_number)
{

    $user_product_journey_id = $proposal->user_product_journey_id;

    $user_journey_data = UserProductJourney::find($user_product_journey_id);
    $product_parent = strtoupper(get_parent_code($user_journey_data->product_sub_type_id));

    $data['user_product_journey_id'] = $user_product_journey_id;
    $data['ic_id'] = $proposal->ic_id;

    $Policy_payment_api_request = [
        'strPolSysId' => $proposal->pol_sys_id
    ];

    $get_response = getWsData(config('constants.bike.shriram.SHRIRAM_POLICY_PDF_URL'), $Policy_payment_api_request, 'shriram', [
        'enquiryId' => $user_product_journey_id,
        'headers' => [
            'Username' => config('constants.motor.shriram.AUTH_NAME_SHRIRAM_MOTOR'),
            'Password' => config('constants.motor.shriram.AUTH_PASS_SHRIRAM_MOTOR'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
        'requestMethod' => 'post',
        'requestType' => 'json',
        'section' => $product_parent,
        'method' => 'Policy PDF ',
        'transaction_type' => 'proposal',
    ]);
    $Policy_PDF_Response = $get_response['response'];

    if ($Policy_PDF_Response) {
        $policy_Pdf_api_res = json_decode($Policy_PDF_Response, TRUE);

        if (empty($policy_Pdf_api_res)) {
            return [
                'status' => true,
                'message' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                'data' => [
                    'policy_number' => $policy_number
                ]
            ];
        }

        if (empty($policy_Pdf_api_res['PolicyScheduleURLResult'])) {
            return [
                'status' => true,
                'message' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                'data' => [
                    'policy_number' => $policy_number
                ]
            ];
        }
        foreach ($policy_Pdf_api_res as $value) {
            if (!empty($value['PolicyScheduleURLResult'] ?? '')) {
                PolicyDetails::updateOrCreate(
                    ['proposal_id' => $proposal->user_proposal_id],
                    [
                        'ic_pdf_url' => $value['PolicyScheduleURLResult'],
                    ]
                );

                return downloadPDFFromURL($policy_number, $value['PolicyScheduleURLResult'],$proposal,$product_parent);
            }
        }
        return [
            'status' => true,
            'message' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
            'data' => [
                'policy_number' => $policy_number
            ]
        ];
    } else {
        return [
            'status' => true,
            'message' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
            'data' => [
                'policy_number' => $policy_number
            ]
        ];
    }
}


function downloadPDFFromURL($policyNumber,$policyURL,$proposal,$section)
{
    $folder = config('constants.motorConstant.' . $section . '_PROPOSAL_PDF_URL');
    $pdf_name = $folder . 'shriram/' . md5($proposal->user_proposal_id) . '.pdf';
    $pdf_data = httpRequestNormal($policyURL, 'GET', [], [], [], [], false);
    if ($pdf_data && $pdf_data['status'] == 200 && (!empty($pdf_data['response'] ?? ''))) 
    {
        Storage::put($pdf_name, $pdf_data['response']);
        PolicyDetails::updateOrCreate(
            ['proposal_id' => $proposal->user_proposal_id],
            [
                'ic_pdf_url'     => $policyURL,
                'pdf_url' => $pdf_name,
            ]
        );
        $data['user_product_journey_id'] = $proposal->user_product_journey_id;
        $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
        updateJourneyStage($data);
        return [
            'status' => true,
            'message' => STAGE_NAMES['POLICY_ISSUED'],
            'data' => [
                'policy_number' => $policyNumber,
                'pdf_url' =>  $pdf_name,
            ]
        ];
    }
}