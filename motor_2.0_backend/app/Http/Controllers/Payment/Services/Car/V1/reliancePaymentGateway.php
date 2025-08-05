<?php
namespace App\Http\Controllers\Payment\Services\Car\V1;

use App\Http\Controllers\Proposal\Services\Car\V1\RelianceSubmitProposal as RelianceSubmitProposalV1;
use App\Models\CvBreakinStatus;
use Exception;
use Config;
use App\Models\UserProposal;
use App\Models\PaymentRequestResponse;
use App\Models\MasterPolicy;
use App\Models\QuoteLog;
use Illuminate\Support\Facades\DB;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\Storage;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';
class reliancePaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $enquiryId = customDecrypt($request->enquiryId);
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $productData = getProductDataByIc($request['policyId']);
        $icId = MasterPolicy::where('policy_id', $request['policyId'])
                ->pluck('insurance_company_id')
                ->first();
        $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
                ->pluck('quote_id')
                ->first();
        $premium_type = DB::table('master_premium_type')
                ->where('id',$productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();
        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
        $UserID = (($tp_only == 'true') && !empty(config('IC.RELIANCE.V1.CAR.TP_USERID'))) ? config('IC.RELIANCE.V1.CAR.TP_USERID') : config('IC.RELIANCE.V1.CAR.USERID');
        
        $requestData = getQuotation($enquiryId);
        if (
            in_array($premium_type, ['breakin', 'own_damage_breakin'])
            || ($requestData->previous_policy_type == 'Third-party'
            && !in_array($premium_type, ['third_party', 'third_party_breakin']))
        ) {

            $breakinDetails = CvBreakinStatus::where('user_proposal_id', $user_proposal->user_proposal_id)->first();

            if (!empty($breakinDetails) && $breakinDetails->breakin_status == STAGE_NAMES['INSPECTION_APPROVED']) {
                $response = RelianceSubmitProposalV1::postInspectionSubmit($user_proposal);
                if (($response['status'] ?? false) != true) {
                    return response()->json([
                        'status' => false,
                        'msg' => $response['message'] ?? 'Something went wrong'
                    ]);
                }

                $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
            } else {
                return response()->json([
                    'status' => false,
                    'msg' => 'Inspection is pending'
                ]);
            }
        }
        $params = [
            'ProposalNo'     => $user_proposal->proposal_no,
            'userID'         => $UserID,
            'ProposalAmount' => $user_proposal->final_payable_amount,
            'PaymentType'    => '1',
            'Responseurl'    => route('car.payment-confirm', ['reliance']),
        ];
        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
            if(empty($user_proposal->ckyc_number))
            {
                return response()->json([
                    'status' => false,
                    'msg' => 'CKYC number is not available, Please complete CKYC process'
                ]);
            }
            $kyc_param = [
                'CKYC'    => $user_proposal->ckyc_number,
                'IsDocumentUpload'    => 'false',
                'PanNo'    => $user_proposal->pan_number,
                'IsForm60'    => 'false'
            ];
            $params = array_merge($params, $kyc_param);
        }

        $params['subscription-key'] = config('IC.RELIANCE.V1.CAR.OCP_APIM_SUBSCRIPTION_KEY');

        $payment_url = config('IC.RELIANCE.V1.CAR.PAYMENT_GATEWAY_LINK'). '?' . http_build_query($params);

        DB::table('payment_request_response')
            ->where('user_product_journey_id', $enquiryId)
            ->update(['active' => 0]);

        DB::table('payment_request_response')->insert([
            'quote_id'                  => $quote_log_id,
            'user_product_journey_id'   => $enquiryId,
            'user_proposal_id'          => $user_proposal->user_proposal_id,
            'ic_id'                     => $icId,
            'order_id'                  => $user_proposal->proposal_no,
            'proposal_no'               => $user_proposal->proposal_no,
            'amount'                    => $user_proposal->final_payable_amount,
            'payment_url'               => $payment_url,
            'return_url'                => route('car.payment-confirm', ['reliance']),
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1,
            'xml_data'                  => json_encode($params)
        ]);

        updateJourneyStage([
            'user_product_journey_id' => $user_proposal->user_product_journey_id,
            'stage' => STAGE_NAMES['PAYMENT_INITIATED']
        ]);

        return response()->json([
            'status' => true,
            'data' => [
                'payment_type' => 1,
                'paymentUrl' => trim($payment_url)
            ]
        ]);
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function confirm($request) {
        $Output = $request->query('Output');
        $response_array = explode('|', $Output);
        $bool_status = $response_array[0];
        $policy_no = $response_array[1];
        $proposal_no = $response_array[5];
        $status = $response_array[6];
        $message = STAGE_NAMES['PAYMENT_FAILED'];

        if(empty($proposal_no))
        {
            return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
        }

        $PaymentRequestResponse = PaymentRequestResponse::where('order_id', $proposal_no)
            ->select('*')
            ->first();

        if(empty($PaymentRequestResponse))
        {
            return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
        }

        $user_proposal = UserProposal::where('user_proposal_id', $PaymentRequestResponse->user_proposal_id)
            ->orderBy('user_proposal_id', 'desc')
            ->select('*')
            ->first();

        PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->where('order_id', $proposal_no)
            ->where('active',1)
            ->update([
                'response' => $request->All(),
                'proposal_no' => $proposal_no,
                'status' => ($bool_status == 1 ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'])
            ]);
            
        $message = $bool_status == 1 ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'];

        $return_url = config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL');

        if (!empty($policy_no)) {
            $user_proposal->policy_no = $policy_no;
            UserProposal::where('user_proposal_id' , $user_proposal->user_proposal_id)->update([
                'policy_no' => $policy_no
            ]);
            PolicyDetails::updateOrCreate(['proposal_id' => $user_proposal->user_proposal_id ], [
                'policy_number' => $policy_no,
                'status' => strtoupper($status),
                'ncb' => $user_proposal->ncb_discount,
                'idv' => $user_proposal->idv,
                'policy_start_date' => $user_proposal->policy_start_date,
                'premium' => $user_proposal->total_premium,
            ]);

            if($bool_status && !empty($proposal_no)) {
                $policy_detail = (object)[
                    'user_proposal_id' => $user_proposal->user_proposal_id,
                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                    'product_code' => $user_proposal->product_code,
                ];
                $generate_pdf = self::createPDF($policy_no, $policy_detail)->getOriginalContent();
                $message = $generate_pdf['msg'];
                $return_url = config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL');
            }
        }

        updateJourneyStage([
            'user_product_journey_id' => $user_proposal->user_product_journey_id,
            'stage' => $message
        ]);
        
        if (!empty($policy_no))
        {
            $enquiryId = $user_proposal->user_product_journey_id;
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));            
        }
        else
        {
            $enquiryId = $user_proposal->user_product_journey_id;
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','FAILURE'));            
        }

        //return redirect($return_url.'?'.http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
    }

    static public function generatePdf($request)
    { 
         
        $user_product_journey_id = customDecrypt($request->enquiryId);

        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin('policy_details as pd','pd.proposal_id','=','prr.user_proposal_id')
            ->join('user_proposal as up','up.user_product_journey_id','=','prr.user_product_journey_id')
            ->where('prr.user_product_journey_id',$user_product_journey_id)
            // ->where('prr.active',1)
            ->select('prr.id', 'prr.user_product_journey_id', 'up.user_proposal_id', 'up.user_proposal_id', 'prr.proposal_no', 'up.unique_proposal_id', 'up.product_code', 'pd.policy_number', 'pd.pdf_url', 'pd.ic_pdf_url'
            )
            ->get();

        if(empty($policy_details))
        {
            return response()->json([
                'status' => false,
                'msg'    => 'Payment is pending'
            ]);
        }

        $return_data = [
            'status' => false,
            'msg'    => 'Payment is pending'
        ];

        foreach ($policy_details as $policy_detail) {
            if(empty($policy_detail) || empty($policy_detail->policy_number))
            {
                $proposalStatusService = self::proposalStatusService($user_product_journey_id, $policy_detail);
                if($proposalStatusService['status']){
                    return self::createPDF($proposalStatusService['data']['policy_number'], $policy_detail);
                }
            }
            else
            {
                if(!empty($policy_detail->pdf_url))
                {
                    if(!Storage::exists(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'reliance/'. md5($policy_detail->user_proposal_id). '.pdf'))
                    {
                        return self::createPDF($policy_detail->policy_number, $policy_detail);
                    }
                    return response()->json([
                        'status' => true,
                        'msg' => 'success',
                        'data' => [
                            'policy_number' => $policy_detail->policy_number,
                            'pdf_link' => file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'reliance/'. md5($policy_detail->user_proposal_id). '.pdf'),//$policy_detail->pdf_url,
                            'ic_pdf_url' => $policy_detail->ic_pdf_url,
                        ]
                    ]);
                }
                return self::createPDF($policy_detail->policy_number, $policy_detail); 
            }
        }
        return $return_data;
    }

    public static function proposalStatusService($enquiryId, $policy_detail)
    {    
        $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $policyid = QuoteLog::where('user_product_journey_id', $enquiryId)->pluck('master_policy_id')->first();
        $productData = getProductDataByIc($policyid);
        $premium_type = DB::table('master_premium_type')
                ->where('id',$productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();
        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
        $UserID = (($tp_only == 'true') && !empty(config('IC.RELIANCE.V1.CAR.TP_USERID'))) ? config('IC.RELIANCE.V1.CAR.TP_USERID') : config('IC.RELIANCE.V1.CAR.USERID');

        $SourceSystemID = (($tp_only == 'true') && !empty(config('IC.RELIANCE.V1.CAR.TP_SOURCE_SYSTEM_ID')) )? config('IC.RELIANCE.V1.CAR.TP_SOURCE_SYSTEM_ID') : config('IC.RELIANCE.V1.CAR.SOURCE_SYSTEM_ID');
        
        $AuthToken = (($tp_only == 'true') && !empty(config('IC.RELIANCE.V1.CAR.TP_AUTH_TOKEN')) ) ? config('IC.RELIANCE.V1.CAR.TP_AUTH_TOKEN') : config('IC.RELIANCE.V1.CAR.AUTH_TOKEN');
        $proposalStatusRequest = [
            'ValidateFlag' => 'false',
            'Policy' => [
                'ProposalNo' => $policy_detail->proposal_no,
                'PolicyNumber' => '',
            ],
            'ErrorMessages' => '',
            'UserID' => $UserID,
            'SourceSystemID' => $SourceSystemID,
            'AuthToken' => $AuthToken,
            'Authentication' => [
                'PolicyNumber' => '',
                'EngineNo' => '',
                'ChassisNo' => '',
                'RegistrationNo' => '',
                'PolicyEndDate' => '',
                'ProposerDOB' => '',
            ],
        ];

        $get_response = getWsData(
            config('IC.RELIANCE.V1.CAR.END_POINT_URL_PROPOSAL_STATUS'),
            $proposalStatusRequest,
            'reliance',
            [
                'root_tag' => 'ProposalDetails',
                'section' => 'Car',
                'method' => 'Proposal Status - Payment',
                'requestMethod' => 'post',
                'enquiryId' => $enquiryId,
                'productName' => $proposal->business_type.' '.$proposal->product_type,
                'transaction_type' => 'proposal',
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => config('IC.RELIANCE.V1.CAR.OCP_APIM_SUBSCRIPTION_KEY'),
                    'Content-type' => 'text/xml'
                ]
            ]
        );
        $proposalStatusResponse = $get_response['response'];

        if(empty($proposalStatusResponse))
        {
            return [
                'status' => false,
                'message' => 'Insurer Not Reachable - Proposal Status'
            ];
        }

        $proposalStatusResponse = json_decode($proposalStatusResponse, true);

        if(empty($proposalStatusResponse))
        {
            return [
                'status' => false,
                'message' => 'Insurer Not Reachable - Proposal Status'
            ];
        }

        if(isset($proposalStatusResponse['ProposalDetails']['Proposal']['PolicyNumber']) && !empty($proposalStatusResponse['ProposalDetails']['Proposal']['PolicyNumber']))
        {
            DB::table('payment_request_response')
                ->where('user_product_journey_id', $enquiryId)
                ->where('proposal_no', $policy_detail->proposal_no)
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);

                PolicyDetails::updateOrCreate(
                [
                    'proposal_id' => $proposal->user_proposal_id
                ],
                [
                    'policy_number' => $proposalStatusResponse['ProposalDetails']['Proposal']['PolicyNumber']
                ]
            );

            return [
                'status' => true,
                'message' => 'success',
                'data' => [
                    'policy_number' => $proposalStatusResponse['ProposalDetails']['Proposal']['PolicyNumber']
                ]
            ];
        }
        else{

            if(isset($proposalStatusResponse['ProposalDetails']['ErrorMessages']) && !empty($proposalStatusResponse['ProposalDetails']['ErrorMessages']))
            {
                return [
                    'status' => false,
                    'message' => $proposalStatusResponse['ProposalDetails']['ErrorMessages']
                ];
            }
            else
            {
                return [
                    'status' => false,
                    'message' => 'Unable To Fetch PolicyNumber - Proposal Status'
                ];
            }

        }
    }

    public static function createPDF($policy_number, $policy_detail)
    {
    
        $user_product_journey_id = $policy_detail->user_product_journey_id;
        if (config('IC.RELIANCE.V1.CAR.POLICY_DWLD_LINK_NEW_API_ENABLE') == 'Y') {
            $ic_pdf_url =  config('IC.RELIANCE.V1.CAR.POLICY_DWLD_LINK_NEW');
            $pdf_Request =
                [
                    'PolicyNumber' => $policy_number,
                    'SourceSystemID' => config('IC.RELIANCE.V1.CAR.SOURCE_SYSTEM_ID'),
                    'SecureAuthToken' => config('IC.RELIANCE.V1.CAR.SECURE_AUTH_TOKEN'),
                    'EndorsementNo' => '',
                ];
            $result = UserProposal::join('quote_log', 'user_proposal.user_product_journey_id', '=', 'quote_log.user_product_journey_id')
            ->where('user_proposal.user_product_journey_id', $user_product_journey_id)
                ->select('user_proposal.*', 'quote_log.quote_id', 'quote_log.master_policy_id')
                ->first();
            $productData = getProductDataByIc($result->master_policy_id);
            $productName = $productData->product_name;
            $productName = str_replace('-', '', $productName);
        } else {
            $ic_pdf_url =  config('IC.RELIANCE.V1.CAR.POLICY_DWLD_LINK') . '?PolicyNo=' . $policy_number . '&ProductCode=' . $policy_detail->product_code;
        }
      
        if (config('constants.motorConstant.RELIANCE_GENERATE_POLICY_PDF') == 'Y') {
            try{
                $pdf_name = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'reliance/'. md5($policy_detail->user_proposal_id). '.pdf';
                if(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->exists($pdf_name))
                {
                   Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->delete($pdf_name);
                }
                if (config('IC.RELIANCE.V1.CAR.POLICY_DWLD_LINK_NEW_API_ENABLE') == 'Y') {
                    
                    $pdf_data = getWsData(
                        $ic_pdf_url,
                        $pdf_Request,
                        'reliance',
                        [
                            'root_tag' => 'GenerateScheduleRequest',
                            'section' => 'Car',
                            'method' => 'Download policy PDF',
                            'enquiryId' => $user_product_journey_id,
                            'productName' => $productName.' ('.ucwords($result->business_type).')',
                            'requestMethod' => 'post',
                            'transaction_type' => 'proposal',
                            'headers' => [
                                'Ocp-Apim-Subscription-Key' => config('IC.RELIANCE.V1.CAR.OCP_APIM_SUBSCRIPTION_KEY'),
                                'Content-type' => 'text/xml'
                            ]
                        ]
                    );
                    $pdf_data = json_decode($pdf_data['response'], true);

                    if (empty($pdf_data['DownloadLink'])) {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);
                        $pdf_response_data = [
                            'status' => true,
                            'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                            'data' => [
                                'policy_number' => $policy_number,
                            ]
                        ];
                        return response()->json($pdf_response_data);
                    }else {
                        $ic_pdf_url = $pdf_data['DownloadLink'];

                        $pdfData = httpRequestNormal($ic_pdf_url, 'GET', [], [], [], [], false)['response'];//file_get_contents($pdf_data['DownloadLink']);

                        if(!checkValidPDFData($pdfData))
                        {
                            updateJourneyStage([
                                'user_product_journey_id' => $user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                            ]);
                            $pdf_response_data = [
                                'status' => true,
                                'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                'data' => [
                                    'policy_number' => $policy_number,
                                ]
                            ];
                            return response()->json($pdf_response_data);
                        }

                        Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'reliance/' . md5($policy_detail->user_proposal_id) . '.pdf', $pdfData);
                    }
                } else {
                    $pdf_data = httpRequestNormal($ic_pdf_url, 'GET', [], [], [], [], false);
                    if (!checkValidPDFData($pdf_data['response'])) {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);
                        $pdf_response_data = [
                            'status' => true,
                            'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                            'data' => [
                                'policy_number' => $policy_number,
                                'ic_pdf_url' => $ic_pdf_url,
                            ]
                        ];
                        return response()->json($pdf_response_data);
                    }
                    if (isset($pdf_data['status']) && $pdf_data['status'] == 200) {
                        Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'reliance/' . md5($policy_detail->user_proposal_id) . '.pdf', $pdf_data['response']);
                    }
                }
            }
            catch(Exception $e)
            {
                updateJourneyStage([
                    'user_product_journey_id' => $user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);
                
                $pdf_response_data = [
                    'status' => true,
                    'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                    'data' => [
                        'policy_number' => $policy_number,
                        'ic_pdf_url' => $ic_pdf_url,
                    ]
                ];
            }
        }

        PolicyDetails::updateOrCreate(
            ['proposal_id' => $policy_detail->user_proposal_id],
            [
                'policy_number' => $policy_number,
                'ic_pdf_url' => $ic_pdf_url,
                'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'reliance/'. md5($policy_detail->user_proposal_id). '.pdf',
                'status' => 'SUCCESS'
            ]
        );
        
        $pdf_url = file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'reliance/'. md5($policy_detail->user_proposal_id). '.pdf');
        $pdf_response_data = [
            'status' => true,
            'msg' => STAGE_NAMES['POLICY_ISSUED'],
            'data' => [
                'policy_number' => $policy_number,
                'pdf_link' => $pdf_url,
                'ic_pdf_url' => $ic_pdf_url,
            ]
        ];

        updateJourneyStage([
            'user_product_journey_id' => $user_product_journey_id,
            'stage' => STAGE_NAMES['POLICY_ISSUED']
        ]);

        return response()->json($pdf_response_data);
    }
}
