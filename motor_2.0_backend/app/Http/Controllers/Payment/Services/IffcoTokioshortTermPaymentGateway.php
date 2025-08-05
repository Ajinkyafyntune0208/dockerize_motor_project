<?php

namespace App\Http\Controllers\Payment\Services;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\MasterPolicy;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use DateTime;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\ArrayToXml\ArrayToXml;
use App\Models\CvBreakinStatus;
use App\Jobs\GeneratePDFJob;

use Carbon\Carbon;

class IffcoTokioshortTermPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {

        $enquiryId      = customDecrypt($request->enquiryId);
        $proposal  = UserProposal::where('user_product_journey_id', $enquiryId)->first();

        $quote_log_id   = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->pluck('quote_id')
            ->first();

        $icId           = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first();

        $productData = getProductDataByIc($request['policyId']);

        if($proposal->is_breakin_case == 'Y')
        {   
            $submitProposalResponse = self::submitProposal($proposal, $productData);

            if(!$submitProposalResponse['status'])
            {
                return [
                    'status' => false,
                    'msg' => $submitProposalResponse['message'],
                    'message' => $submitProposalResponse['message']
                ];
            }
        }

        $proposal  = UserProposal::where('user_product_journey_id', $enquiryId)->first();

        $additional_details = json_decode($proposal->additional_details_data, true);

        $return_data = [
            'form_action' => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PAYMENT_URL_SHORT_TERM'),
            'form_method' => 'post',
            'payment_type' => 0, // form-submit
            'form_data' => [
                'ptnrTransactionLogId' => $additional_details['iffco']['ptnrTransactionLogId'],
                'orderNo' => $additional_details['iffco']['orderNo'],
                'traceNo' => $additional_details['iffco']['traceNo'],
            ]
        ];

        $data['user_product_journey_id'] = $proposal->user_product_journey_id;
        $data['ic_id'] = $proposal->ic_id;
        $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($data);

        DB::table('payment_request_response')
            ->where('user_product_journey_id', $proposal->user_product_journey_id)
            ->where('user_proposal_id', $proposal->user_proposal_id)
            ->update([
                'active'      => 0,
                // 'updated_at'    => date('Y-m-d H:i:s')
            ]);

        DB::table('payment_request_response')->insert([
            'quote_id'                  => $quote_log_id,
            'user_product_journey_id'   => $enquiryId,
            'user_proposal_id'          => $proposal->user_proposal_id,
            'ic_id'                     => $icId,
            'proposal_no'               => $proposal->proposal_no,
            'order_id'                  => $proposal->unique_proposal_id,
            'amount'                    => $proposal->final_payable_amount,
            'payment_url'               => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PAYMENT_URL_SHORT_TERM'),
            'return_url'                => route(
                'cv.payment-confirm',
                [
                    'iffco_tokio',
                    'user_proposal_id'      => $proposal->user_proposal_id,
                    'policy_id'             => $request->policyId
                ]
            ),
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1,
            'xml_data'                  => json_encode($return_data)
        ]);

        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);
    }

    public static function confirm($request)
    {
        $response = explode('|', $request->ITGIResponse);

        $order_no = $response[1];
        $policy_no = $response[3];
        $premium = $response[4];
        $status = $response[5];


        $success_callback = config('constants.motorConstant.CV_PAYMENT_SUCCESS_CALLBACK_URL');
        $failure_callback = config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL');

        $proposal = UserProposal::where('proposal_no', $order_no)->first();

        DB::table('payment_request_response')
            ->where('user_product_journey_id', $proposal->user_product_journey_id)
            ->where('user_proposal_id', $proposal->user_proposal_id)
            ->where('proposal_no', $order_no)
            ->where('active', 1)
            ->update([
                'response'      => $request->ITGIResponse,
                'updated_at'    => date('Y-m-d H:i:s')
            ]);

        if ($status != 'SUCCESS') {
            DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->where('active', 1)
                ->update([
                    'status'        => STAGE_NAMES['PAYMENT_FAILED']
                ]);

            $data['user_product_journey_id'] = $proposal->user_product_journey_id;
            $data['ic_id'] = $proposal->ic_id;
            $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            updateJourneyStage($data);

            return redirect($failure_callback . '?' . http_build_query([
                'enquiry_id' => customEncrypt($proposal->user_product_journey_id)
            ]));
        } else {
            DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->where('active', 1)
                ->update([
                    'status'        => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['PAYMENT_SUCCESS'],
                ]);

            if(empty($policy_no))
            {
                return redirect($success_callback . '?' . http_build_query([
                    'enquiry_id' => customEncrypt($proposal->user_product_journey_id)
                ]));
            }
        }

        PolicyDetails::updateOrCreate(['proposal_id' => $proposal->user_proposal_id], [
            'policy_number' => $policy_no,
        ]);

        $proposal->policy_no = $policy_no;
        $proposal->save();

        updateJourneyStage([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
        ]);

        GeneratePDFJob::dispatch('iffco_tokio','cv_short_term', $proposal)->delay(now()->addMinutes(5));
        // $pdServiceData = self::policyPDFService($proposal, $policy_no);

        return redirect($success_callback . '?' . http_build_query([
            'enquiry_id' => customEncrypt($proposal->user_product_journey_id)
        ]));
    }
    // End confirm method

    public static function policyPDFService($proposal, $policy_no)
    {
        $partnerCode = config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE_SHORT_TERM');
        $partnerPass = config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_PASSWORD_SHORT_TERM');

        $pdfServiceRequest = [
            "contractType" => "CVI",
            "policyDownloadNo" => $policy_no,
            "partnerDetail" => [
                "partnerCode" => $partnerCode,
            ],
        ];

        $additional_data = [
            'requestMethod' => 'post',
            'company' => 'iffco_tokio',
            'requestType' => 'json',
            'productName' => 'CV Insurance short term',
            'section' => 'PCV',
            'method' => 'PDF Generation - Payment',
            'enquiryId' => $proposal->user_product_journey_id,
            'transaction_type' => 'proposal',
            'headers' => [
                "Authorization: Basic " . base64_encode($partnerCode . ":" . $partnerPass),
                "Content-Type: application/json"
            ],
        ];

        $get_response = getWsData(config('constants.cv.iffco.IFFCO_TOKIO_PCV_POLICY_PDF_SHORT_TERM'), $pdfServiceRequest, 'iffco_tokio', $additional_data);
        $pdfServiceResponse = $get_response['response'];

        if (empty($pdfServiceResponse)) {
            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
            ]);

            return [
                'status' => true,
                'message' => 'No response from PDF service',
                'data' => [
                    'policy_number' => $policy_no,
                ]
            ];
        }

        $pdfServiceResponse = json_decode($pdfServiceResponse, 1);

        if (empty($pdfServiceResponse)) {
            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
            ]);

            return [
                'status' => true,
                'message' => 'No response from PDF service',
                'data' => [
                    'policy_number' => $policy_no,
                ]
            ];
        }

        if (!isset($pdfServiceResponse['statusMessage']) || strtoupper($pdfServiceResponse['statusMessage']) != 'SUCCESS') {
            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
            ]);

            if (isset($pdfServiceResponse['error']) && !empty($pdfServiceResponse['error'])) {
                if(!is_array($pdfServiceResponse['error']))
                {
                    return [
                        'status' => true,
                        'message' => $pdfServiceResponse['error'],
                        'data' => [
                            'policy_number' => $policy_no,
                        ]
                    ];
                }
                else if(count($pdfServiceResponse['error']) > 0)
                {
                    return [
                        'status' => true,
                        'message' => implode(', ', array_column($pdfServiceResponse['error'], 'errorMessage')),
                        'data' => [
                            'policy_number' => $policy_no,
                        ]
                    ];
                }
                else
                {
                    return [
                        'status' => true,
                        'message' => 'No response from PDF service',
                        'data' => [
                            'policy_number' => $policy_no,
                        ]
                    ];
                }
            }
            else
            {
                return [
                    'status' => true,
                    'message' => 'No response from PDF service',
                    'data' => [
                        'policy_number' => $policy_no,
                    ]
                ];
            }
        }

        $ic_pdf_url = $pdfServiceResponse['policyDownloadLink'];
        $pdf_url = config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'iffco_tokio/' . md5($proposal->user_proposal_id) . '.pdf';

        PolicyDetails::updateOrCreate([
            'proposal_id' => $proposal->user_proposal_id
        ],
        [
            'policy_number' => $policy_no,
            'ic_pdf_url' => $ic_pdf_url,
        ]);
        try {
            $pdf_name = config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'iffco_tokio/' . md5($proposal->user_proposal_id) . '.pdf';


            // Storage::put($pdf_url, file_get_contents($ic_pdf_url));
            // Storage::put($pdf_url, httpRequestNormal($ic_pdf_url, 'GET', [], [], [], [], false)['response']);


            $pdfGenerationResponse = httpRequestNormal($ic_pdf_url, 'GET', [], [], [], [], false)['response'];

            if(!preg_match("/^%PDF-/", $pdfGenerationResponse))
            {   
                return response()->json( [
                    'status' => true,
                    'message' => 'Not getting valid PDF data from service',
                    'data' => [
                        'policy_number' => $policy_no,
                        'ic_pdf_url' => $ic_pdf_url,
                    ]
                ]);
            }
            if(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->exists($pdf_name))
            {
               Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->delete($pdf_name);
            }
            Storage::put($pdf_name, $pdfGenerationResponse);

            PolicyDetails::updateOrCreate([
                'proposal_id' => $proposal->user_proposal_id
            ],
            [
                'policy_number' => $policy_no,
                'pdf_url' => $pdf_url,
            ]);

            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED'],
            ]);

            return [
                'status' => true,
                'message' => 'success',
                'data' => [
                    'policy_number' => $policy_no,
                    'pdf_url' => $pdf_url,
                    'ic_pdf_url' => $ic_pdf_url,
                    'pdf_link' => file_url($pdf_url) //Storage::url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'iffco_tokio/'. md5($proposal->user_proposal_id) . '.pdf'),
                ]
            ];
        } catch (\Throwable $e) {
            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
            ]);

            return [
                'status' => true,
                'message' => 'No response from PDF service',
                'data' => [
                    'policy_number' => $policy_no,
                ]
            ];
        }
    }


    public static function generate_pdf($user_proposal, $is_rehit = 'N')
    {
        $status = false;
        $message = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
        $request_array = [
            'contractType' => 'CVI',
            'policyDownloadNo' => $user_proposal->policy_no,
            'partnerDetail' => [
                'partnerCode' => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE_SHORT_TERM')
            ]
        ];

        $u = config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE_SHORT_TERM');
        $p = config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_PASSWORD_SHORT_TERM');
        $get_response = getWsData(
            config('constants.cv.iffco.IFFCO_TOKIO_PCV_POLICY_PDF_SHORT_TERM'),
            $request_array,
            'iffco_tokio',
            [
                'requestMethod' => 'post',
                'company'  => 'iffco_tokio',
                'requestType' => 'json',
                'productName'  => 'CV Insurance short term',
                'section' => 'CV',
                'method' => 'PDF Generation',
                'enquiryId' => $user_proposal->user_product_journey_id,
                'transaction_type' => 'proposal',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode("$u:$p"),
                    'Accept' => 'application/json',
                    'Content-Length' => strlen(json_encode($request_array)),
                ],
            ]
        );
        $data = $get_response['response'];

        if (!empty($data)) {
            $data = (array) json_decode($data);
            if ( isset($data['statusMessage']) && strtoupper($data['statusMessage']) == 'SUCCESS') {
                $pdf_url = $data['policyDownloadLink'];
                $pdf_name = config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'iffco_tokio/' .  md5($user_proposal->user_proposal_id) . '.pdf';
                PolicyDetails::updateOrCreate(['proposal_id' => $user_proposal->user_proposal_id], [
                    'policy_number' => $user_proposal->policy_no,
                    'ic_pdf_url' => $pdf_url,
                ]);
                try {


                    $pdfGenerationResponse = httpRequestNormal($pdf_url, 'GET', [], [], [], [], false)['response'];

                    if(!preg_match("/^%PDF-/", $pdfGenerationResponse))
                    {
                        GeneratePDFJob::dispatch('iffco_tokio','cv_short_term', $user_proposal)->delay(now()->addMinutes(7));
                        
                        return response()->json([
                            'status' => false,
                            'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                            'pdf_name' => $pdf_name ?? null
                        ]);
                    }
                    if(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->exists($pdf_name))
                    {
                       Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->delete($pdf_name);
                    }
                    Storage::put($pdf_name, $pdfGenerationResponse);

                    PolicyDetails::updateOrCreate(['proposal_id' => $user_proposal->user_proposal_id], [
                        'policy_number' => $user_proposal->policy_no,
                        'pdf_url' => $pdf_name,
                    ]);
                    $status = true;
                    $message = STAGE_NAMES['POLICY_ISSUED'];
                    updateJourneyStage([
                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                        'stage' => $message
                    ]);
                } catch (\Throwable $e) {
                    // Log PDF generation fail error
                }
            }
        }

        return response()->json([
            'status' => $status,
            'msg' => $message,
            'pdf_name' => $pdf_name ?? null
        ]);
    }

    public static function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);

        $payment_status = PaymentRequestResponse::where([
            'user_product_journey_id' => $user_product_journey_id,
        ])->get();

        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        $success = false;
        // if (empty($payment_status)) {
        //     $policyStatusServiceResponse = self::policyStatusService($proposal);
        //     if($policyStatusServiceResponse['status'])
        //     {
        //         $generate_pdf = self::policyPDFService($proposal, $policyStatusServiceResponse['policyNo']);
        //         return $generate_pdf;
        //     }
        //     return response()->json([
        //         'status' => false,
        //         'msg'    => 'Payment is pending'
        //     ]);
        // }
        foreach ($payment_status as $payment) {
            $policyStatusServiceResponse = self::policyStatusService($proposal);
        
            if ($policyStatusServiceResponse['status']) {
                $generate_pdf = self::policyPDFService($proposal, $policyStatusServiceResponse['policyNo']);
                $success = true;
                break;
            }
        }
        if($success){
            return $generate_pdf;
        }


        // $policyDetails = PolicyDetails::where('proposal_id', $proposal->user_proposal_id)->get()->first();
        // if(!empty($policyDetails) && !empty($policyDetails->policy_number) && !empty($policyDetails->pdf_url))
        // {
        //     return [
        //         'status' => true,
        //         'message' => 'success',
        //         'data' => [
        //             'policy_number' => $policyDetails->policy_number,
        //             'pdf_url' => $policyDetails->pdf_url,
        //             'pdf_link' => Storage::url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'iffco_tokio/'. md5($policyDetails->proposal_id) . '.pdf'),
        //         ]
        //     ];
        // }

        if(empty($proposal->policy_no))
        {
            $policyStatusServiceResponse = self::policyStatusService($proposal);
            if($policyStatusServiceResponse['status'])
            {
                $generate_pdf = self::policyPDFService($proposal, $policyStatusServiceResponse['policyNo']);
                return $generate_pdf;
            }
        }
        $generate_pdf = self::policyPDFService($proposal, $proposal->policy_no);

        return $generate_pdf;
    }

    static public function submitProposal($proposal, $productData)
    {
        $enquiryId = $proposal->user_product_journey_id;

        $partnerCode = config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE_SHORT_TERM');
        $partnerPass = config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_PASSWORD_SHORT_TERM');

        $additional_details = json_decode($proposal->additional_details_data, true);

        $premium_type = $additional_details['iffco']['premium_type'];

        $is_three_months    = (in_array($premium_type, ['short_term_3', 'short_term_3_breakin']) ? true : false);

        $proposalServiceRequest = $additional_details['iffco']['proposalServiceRequest'];
        $quoteServiceRequest = $additional_details['iffco']['quoteServiceRequest'];

        $breakinDetails = DB::table('cv_breakin_status')
            ->where([
                'user_proposal_id' => $proposal->user_proposal_id,
                'breakin_status' => STAGE_NAMES['INSPECTION_APPROVED']
            ])
            ->select('*')
            ->first();

        $policy_start_date = Carbon::parse(date('d-m-Y'));
        $policy_end_date = Carbon::parse($policy_start_date)->addMonth($is_three_months ? 3 : 6)->subDay(1);

        $proposal_request['PolicyStartDate'] = $policy_start_date;
        $proposal_request['PolicyEndDate'] = $policy_end_date;
    
        if(empty($breakinDetails))
        {
            return [
                'status' => false,
                'message' => 'Breakin details not Found'
            ];
        }

        $inspectionResponse =  json_decode($breakinDetails->breakin_response, true);

        if(empty($inspectionResponse) || !isset($inspectionResponse['data']['result'][0]['updatedAt']))
        {
            return [
                'status' => false,
                'message' => 'Post inspection details not Found'
            ];
        }

        $breakinupdatedAt = $inspectionResponse['data']['result'][0]['updatedAt'];

        if(Carbon::now() > Carbon::parse($breakinupdatedAt)->addDay(3))
        {
            return [
                'status' => false,
                'message' => 'Payment is Expired',
                'reason' => "payment is valid till inspection date + 72 hours or 3 days"
            ];
        }

        $inspectionDate = date('d/m/Y', strtotime($breakinDetails->inspection_date));

        // quote service
        $quoteServiceRequest["uniqueReferenceNo"] = config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE_SHORT_TERM') . time() . rand(10, 99);

        $proposalServiceRequest["commercialVehicle"]["inceptionDate"] = $quoteServiceRequest["commercialVehicle"]["inceptionDate"] =  $policy_start_date->format('d/m/Y');
        $proposalServiceRequest["commercialVehicle"]["expirationDate"] = $quoteServiceRequest["commercialVehicle"]["expirationDate"] = $policy_end_date->format('d/m/Y');

        $quoteServiceRequest["commercialVehicle"]["inspectionAgency"] = 'LiveChek';
        $quoteServiceRequest["commercialVehicle"]["inspectionDate"] = $inspectionDate;
        $quoteServiceRequest["commercialVehicle"]["inspectionNo"] = $breakinDetails->breakin_id;
        $quoteServiceRequest["commercialVehicle"]["inspectionStatus"] = 'APPROVED';
        $inspectionDate = date('d/m/Y', strtotime($breakinDetails->inspection_date));
        // end quote service

        // proposal service
        // $proposalServiceRequest["commercialVehicle"]["inceptionDate"] =  $policy_start_date->format('d/m/Y');
        // $proposalServiceRequest["commercialVehicle"]["expirationDate"] = $policy_end_date->format('d/m/Y');

        $proposalServiceRequest["commercialVehicle"]["inspectionAgency"] = 'LiveChek';
        $proposalServiceRequest["commercialVehicle"]["inspectionDate"] = $inspectionDate;
        $proposalServiceRequest["commercialVehicle"]["inspectionNo"] = $breakinDetails->breakin_id;
        $proposalServiceRequest["commercialVehicle"]["inspectionStatus"] = 'APPROVED';
        // end proposal service

        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' => [
                "Authorization: Basic " . base64_encode($partnerCode . ":" . $partnerPass),
                "Content-Type: application/json"
            ],
            'requestMethod' => 'post',
            'requestType' => 'JSON',
            'section' =>  'PCV',
            'method' => 'premium Calculation - Proposal Breakin',
            'productName' => $productData->product_name,
            'transaction_type' => 'proposal',
        ];
    
        $get_response = getWsData(config('constants.cv.iffco.IFFCO_TOKIO_PCV_QUOTE_URL_SHORT_TERM'), $quoteServiceRequest, 'iffco_tokio', $additional_data);
        $quoteServiceResponse = $get_response['response'];


        if (empty($quoteServiceResponse)) {
            return [
                'premium_amount' => '0',
                'status' => 'false',
                'message' => 'Insurer not Reacheable',
            ];
        }
    
        $quoteServiceResponse = json_decode($quoteServiceResponse, true);

        if ($quoteServiceResponse === null || empty($quoteServiceResponse)) {
            return [
                'premium_amount' => '0',
                'status' => 'false',
                'message' => 'Insurer not Reacheable',
            ];
        }

        if (isset($quoteServiceResponse['error']) && !empty($quoteServiceResponse['error'])) {
            if(!is_array($quoteServiceResponse['error']))
            {
                return [
                    'premium_amount' => '0',
                    'status' => false,
                    'message' => $quoteServiceResponse['error'],
                ];
            }
            else if(count($quoteServiceResponse['error']) > 0)
            {
                return [
                    'premium_amount' => '0',
                    'status' => false,
                    'message' => implode(', ', array_column($quoteServiceResponse['error'], 'errorMessage')),
                ];
            }
            else
            {
                return [
                    'premium_amount' => '0',
                    'status' => false,
                    'message' => 'Insurer not Reacheable',
                ];
            }
        }

        $premiumData = $quoteServiceResponse;

        $odPremium = $nonElectricalPremium = $electricalPremium = $cngOdPremium = $totalOdPremium = 0;
        $tpPremium = $legalLiabilityToDriver = $paUnnamed = $cngTpPremium = $totalTpPremium = 0;
        $ncbAmount = $aaiDiscount = $antiTheft = $voluntaryDeductible = $otherDiscount = $tppdDiscount = $totalDiscountPremium = 0;
    
        $odPremium = isset($premiumData['basicOD']) ? round(abs($premiumData['basicOD'])) : 0;
        $electricalPremium = isset($premiumData['electricalOD']) ? round(abs($premiumData['electricalOD'])) : 0;
        $cngOdPremium = isset($premiumData['cngOD']) ? round(abs($premiumData['cngOD'])) : 0;
    
        $totalOdPremium = $odPremium + $nonElectricalPremium + $electricalPremium + $cngOdPremium;
    
        $tpPremium = isset($premiumData['basicTP']) ? round(abs($premiumData['basicTP'])) : 0;
        $legalLiabilityToDriver = isset($premiumData['llDriverTP']) ? round(abs($premiumData['llDriverTP'])) : 0;
        $paUnnamed = isset($premiumData['paPassengerTP']) ? round(abs($premiumData['paPassengerTP'])) : 0;
        $cngTpPremium = isset($premiumData['cngTP']) ? round(abs($premiumData['cngTP'])) : 0;
    
        $totalTpPremium = $tpPremium + $legalLiabilityToDriver + $paUnnamed + $cngTpPremium;
    
        $paOwnerDriver = isset($premiumData['paOwnerDriverTP']) ? round(abs($premiumData['paOwnerDriverTP'])) : 0;
    
        $ncbAmount = isset($premiumData['ncb']) ? round(abs($premiumData['ncb'])) : 0;

        $antiTheft = isset($premiumData['antiTheftDisc']) ? round(abs($premiumData['antiTheftDisc'])) : 0;
        $voluntaryDeductible = isset($premiumData['voluntaryExcessDisc']) ? round(abs($premiumData['voluntaryExcessDisc'])) : 0;

        $tppdDiscount = isset($premiumData['tppdDiscount']) ? round(abs($premiumData['tppdDiscount'])) : 0;
    
        $otherDiscount = isset($premiumData['premiumDiscount']) ? round(abs($premiumData['premiumDiscount'])) : 0;
    
        $totalDiscountPremium = $ncbAmount + $aaiDiscount + $antiTheft + $voluntaryDeductible + $otherDiscount + $tppdDiscount;

        $zero_dep_amount = isset($premiumData['nilDep']) ? round(abs($premiumData['nilDep'])) : 0;
        $consumable_amount = isset($premiumData['consumablePrem']) ? round(abs($premiumData['consumablePrem'])) : 0;

        $totalAddonPremium = $zero_dep_amount + $consumable_amount;

        $totalBasePremium = $premiumData['grossPremiumAfterDiscount'];
        $serviceTax = round(abs($totalBasePremium * 18/100));
        $totalPayableAmount = round(abs($totalBasePremium * (1 + (18/100))));




        $proposalServiceRequest["commercialVehicle"]["premiumPayble"] = $quoteServiceResponse['premiumPayble'];

        $proposalServiceRequest["uniqueReferenceNo"] = config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE_SHORT_TERM') . time() . rand(10, 99);

        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' => [
                "Authorization: Basic " . base64_encode($partnerCode . ":" . $partnerPass),
                "Content-Type: application/json"
            ],
            'requestMethod' => 'post',
            'requestType' => 'JSON',
            'section' =>  'PCV',
            'method' => 'Proposal submission - Proposal Breakin',
            'productName' => $productData->product_name,
            'transaction_type' => 'proposal',
        ];

        $get_response = getWsData(config('constants.cv.iffco.IFFCO_TOKIO_PCV_PROPOSAL_URL_SHORT_TERM'), $proposalServiceRequest, 'iffco_tokio', $additional_data);
        $proposalServiceResponse = $get_response['response'];

        if (empty($proposalServiceResponse)) {
            return [
                'status' => false,
                'message' => 'Insurer not Reacheable',
            ];
        }
    
        $proposalServiceResponse = json_decode($proposalServiceResponse, true);

        if ($proposalServiceResponse === null || empty($proposalServiceResponse)) {
            return [
                'status' => false,
                'message' => 'Insurer not Reacheable',
            ];
        }

        if (isset($proposalServiceResponse['error']) && !empty($proposalServiceResponse['error'])) {
            if(!is_array($proposalServiceResponse['error']))
            {
                return [
                    'status' => false,
                    'message' => $proposalServiceResponse['error'],
                ];
            }
            else if(count($proposalServiceResponse['error']) > 0)
            {
                return [
                    'status' => false,
                    'message' => implode(', ', array_column($proposalServiceResponse['error'], 'errorMessage')),
                ];
            }
            else
            {
                return [
                    'status' => false,
                    'message' => 'Insurer not Reacheable',
                ];
            }
        }

        if(isset($proposalServiceResponse['statusMessage']) && $proposalServiceResponse['statusMessage'] != 'SUCCESS')
        {
            return [
                'status' => false,
                'message' => 'Proposal Service status - '.$proposalServiceResponse['statusMessage'],
            ];
        }

        $totalOdPremium = $odPremium + $nonElectricalPremium + $electricalPremium + $cngOdPremium;
        $totalTpPremium = $tpPremium + $legalLiabilityToDriver + $paUnnamed + $cngTpPremium;
        $totalDiscountPremium = $ncbAmount + $aaiDiscount + $antiTheft + $voluntaryDeductible + $otherDiscount + $tppdDiscount;

        $proposal->policy_start_date = $policy_start_date->format('d-m-Y');
        $proposal->policy_end_date = $policy_end_date->format('d-m-Y');

        $proposal->proposal_no = $proposalServiceResponse['orderNo'];
        $proposal->unique_proposal_id = $proposalServiceRequest["uniqueReferenceNo"];

        $proposal->od_premium = round($totalOdPremium) - ($totalDiscountPremium - $tppdDiscount);
        $proposal->tp_premium = $totalTpPremium + $paOwnerDriver;
        $proposal->totalTpPremium = $totalTpPremium;
        $proposal->addon_premium = $totalAddonPremium;
        $proposal->cpa_premium = $paOwnerDriver;
        $proposal->final_premium = round($totalBasePremium);
        $proposal->total_premium = round($totalBasePremium);
        $proposal->service_tax_amount = round($serviceTax);
        $proposal->final_payable_amount = round($totalPayableAmount);

        $proposal->ncb_discount = abs($ncbAmount);
        $proposal->total_discount = $totalDiscountPremium;
        $proposal->electrical_accessories = $electricalPremium;
        $proposal->unique_quote = $proposalServiceResponse['orderNo'];
        $proposal->non_electrical_accessories = $nonElectricalPremium;
        $proposal->policy_type = 'short_term';

        $additional_details['iffco']['ptnrTransactionLogId'] = $proposalServiceResponse['ptnrTransactionLogId'];
        $additional_details['iffco']['orderNo'] = $proposalServiceResponse['orderNo'];
        $additional_details['iffco']['traceNo'] = $proposalServiceResponse['traceNo'];
        $additional_details['iffco']['proposalServiceRequest'] = $proposalServiceRequest;
        $additional_details['iffco']['quoteServiceRequest'] = $quoteServiceRequest;
        $additional_details['iffco']['premium_type'] = $premium_type;

        $proposal->additional_details_data = $additional_details;

        $proposal->save();

        // echo '<pre>'; print_r([$proposalServiceResponse, $proposalServiceRequest]); echo '</pre';die();

        return [
            'status' => true,
            'message' => 'Proposal Submited',
            'data' => [
                'proposalId' => $proposal->user_proposal_id,
                'userProductJourneyId' => $proposal->user_product_journey_id,
                'proposalNo' => $proposalServiceResponse['orderNo'],
                'finalPayableAmount' => $proposal->final_payable_amount,
                'is_breakin' => 'Y',
            ]
        ];

    }


    public static function policyStatusService($proposal)
    {
        $cred = self::getcreds();

        $additional_details = json_decode($proposal->additional_details_data, true);

        $policyStatusRequest = [
            "uniqueReferenceNo" => $proposal->unique_proposal_id,
            "partnerDetail" => [
                "partnerCode" => $cred->partnerCode,
            ],
        ];

        $additional_data = [
            'requestMethod' => 'post',
            'company' => 'iffco_tokio',
            'requestType' => 'json',
            'productName' => 'CV Insurance short term',
            'section' => 'PCV',
            'method' => 'policy Status - Payment',
            'enquiryId' => $proposal->user_product_journey_id,
            'transaction_type' => 'proposal',
            'headers' => [
                "Authorization: Basic " . base64_encode($cred->partnerCode . ":" . $cred->partnerPass),
                "Content-Type: application/json"
            ],
        ];
        $data = self::getServiceResponse(config('constants.cv.iffco.IFFCO_TOKIO_PCV_POLICY_STATUS_SHORT_TERM'), $policyStatusRequest, $additional_data);


        if (!$data['status']) {
            return $data;
        }

        $policyStatusResponse = $data['data'];

        if (isset($policyStatusResponse['paymenstStatus'])) {
            if($policyStatusResponse['paymenstStatus'] == 'RECEIVED' || !empty($policyStatusResponse['policyNo']))
            {
                if(isset($policyStatusResponse['policyNo']))
                {
                    PolicyDetails::updateOrCreate(
                        [
                            'proposal_id' => $proposal->user_proposal_id
                        ],
                        [
                            'policy_number' => $policyStatusResponse['policyNo']
                        ]
                    );
                }

                PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'active'      => 0,
                    ]);

                PaymentRequestResponse::where([
                    'user_proposal_id' => $proposal->user_proposal_id,
                    'proposal_no' => $proposal->proposal_no,
                    'order_id' => $proposal->unique_proposal_id
                ])->orderBy('id', 'DESC')
                ->first()
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                    'active' => 1
                ]);

                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                ]);

                return [
                    'status' => true,
                    'policyNo' => $policyStatusResponse['policyNo']
                ];
            }
            else
            {
                return [
                    'status' => false,
                    'message' => 'Policy Status service '. $policyStatusResponse['paymenstStatus']
                ];   
            }
        }
        else
        {
            return [
                'status' => false,
                'message' => 'No response from Policy Status service'
            ];
        }

    }

    public static function getcreds()
    {
        return (object)[
            'partnerCode' => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE_SHORT_TERM'),
            'partnerPass' => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_PASSWORD_SHORT_TERM')
        ];
    }

    public static function getServiceResponse($url, $request, $additionalData)
    {
        $get_response = getWsData($url, $request, 'iffco_tokio', $additionalData);
        $response = $get_response['response'];

        if (empty($response)) {
            return [
                'status' => false,
                'message' => 'No response from policy Status service'
            ];
        }

        $response = json_decode($response, 1);

        if (empty($response)) {
            return [
                'status' => false,
                'message' => 'No response from policy Status service'
            ];
        }
        return ['status' => true, 'data' => $response];
    }
}
