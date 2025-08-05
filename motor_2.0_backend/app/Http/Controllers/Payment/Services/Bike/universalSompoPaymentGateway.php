<?php

namespace App\Http\Controllers\Payment\Services\Bike;

use App\Models\MasterPolicy;
use App\Models\MasterPremiumType;
use App\Models\PaymentRequestResponse;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;
use App\Http\Controllers\Proposal\Services\Bike\universalSompoSubmitProposal;
use Illuminate\Support\Facades\DB;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';



class universalSompoPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {

        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
        if ($proposal) {

            $productData = getProductDataByIc($request['policyId']);
            $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();


            $enquiryId = customDecrypt($request['userProductJourneyId']);

            $icId = MasterPolicy::where('policy_id', $request['policyId'])->pluck('insurance_company_id')->first();

            $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)->pluck('quote_id')->first();

            $paymentUrl =  config('constants.IcConstants.universal_sompo.UNIVERSAL_SOMPO_PAYMENT_END_POINT_URL').'PosPolicyNo='.$proposal->proposal_no . '&FinalPremium=' . $proposal->final_payable_amount . '&Src=WA' . '&SubSrc=' . config('constants.IcConstants.universal_sompo.AUTH_CODE_SOMPO_MOTOR');

            PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
               // ->where('user_proposal_id', $enquiryId)
                ->update(['active' => 0]);

            PaymentRequestResponse::insert([
                'quote_id'                  => $quote_log_id,
              //  'user_product_journey_id'   => $request['userProductJourneyId'],
                'user_product_journey_id'   => $enquiryId,
                'ic_id'                     => $icId,
                'payment_url'               => $paymentUrl,
                'proposal_no'               => $proposal->proposal_no,
                'order_id'                  => $proposal->proposal_no,
                'amount'                    => $proposal->final_payable_amount,
                'user_proposal_id' => $proposal->user_proposal_id,
                'return_url'                => route('bike.payment-confirm', ['universal_sompo']),
                'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
                'active'                    => 1
            ]);
             updateJourneyStage([
                'user_product_journey_id' => $enquiryId,
                'stage' => STAGE_NAMES['PAYMENT_INITIATED']
            ]);


            $output =self::update_pg_response($request['userProductJourneyId']);
            if($output['status'] == 'true')
            {
                 /* updateJourneyStage([
                'user_product_journey_id' => $enquiryId,
                  'stage' => STAGE_NAMES['POLICY_ISSUED']
                ]); */

                return [
                            'status' => false,
                            'message' => 'Payment already done for this proposal ,you can not do payment for same proposal again.'
                        ];
            }


           
            return response()->json([
                'status' => true,
                'data' => [
                    "payment_type" => '1',
                    "paymentUrl" => $paymentUrl,
                ],
            ]);
        } else {
            return [
                'status' => false,
                'message' => 'proposal data not found'
            ];
        }
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function confirm($request)
    {
       
        $response_msg = explode('|', $request->MSG);
        $pos_policy_no  = $response_msg[1];
        $WAURNNo        = $response_msg[2]; // stored in order id
        $policy_no      = $response_msg[3];
        $premium_amount = $response_msg[4];
        $amount         = $response_msg[6];
        $status_code    = $response_msg[7];
        $payment_status = $response_msg[8];
        $pdf_url        = $response_msg[9];
         //PDF URL CHANGES
        $WACode = explode('|', $request->WACode);
        if(!empty($pos_policy_no) && !empty($WACode)){
            $pdf_url = $response_msg[9] . '&PosPolicyNo=' . $request->PosPolicyNo . '&LOB='.$request->LOB . '&WACode=' . $WACode[0];
            if (!empty($pdf_url) && strpos($pdf_url, ' ') !== false) {
                $pdf_url = str_replace(' ', '%20', $pdf_url);
            }
        }else
        {
            return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        $proposal = UserProposal::where('proposal_no',$pos_policy_no)->select('*')->get();
        $enquiryId = $proposal[0]->user_product_journey_id;

        if ($payment_status == 'Payment successfully.' || $status_code == '1001') 
        {

            PaymentRequestResponse::where('proposal_no',  $pos_policy_no)
                ->where('user_product_journey_id', $enquiryId)
                ->where('active',1)
                ->update([
                    //"order_id" => $WAURNNo,
                    "status" => STAGE_NAMES['PAYMENT_SUCCESS'],
                   // "amount" => $amount,
                    "response" => $request->MSG,
                ]);

            $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();

            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['PAYMENT_SUCCESS']
            ]);

            PolicyDetails::create([
                'proposal_id' => $proposal->user_proposal_id,
                'policy_number' => $policy_no,
                'ic_pdf_url' => $pdf_url,
                'premium' => $premium_amount,
               // 'status' => 'Payment Completed',
            ]);
            UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id',$proposal->user_proposal_id)
                    ->update([
                        'policy_no'             => $policy_no
            ]);


            updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
            ]);
            sleep(8);
            Self::rehitpdf($enquiryId, $policy_no, $pdf_url);

            
            #return redirect(config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'BIKE','SUCCESS'));
        }
        else{
            PaymentRequestResponse::where('proposal_no',  $pos_policy_no)
            ->where('user_product_journey_id', $enquiryId)
            ->where('active',1)
            ->update([
                //"order_id" => $WAURNNo,
                "status" => STAGE_NAMES['PAYMENT_FAILED'],
                //"amount" => $amount,
                "response" =>$request->MSG,
            ]);

            updateJourneyStage([
                'user_product_journey_id' => $enquiryId,
                'stage' => STAGE_NAMES['PAYMENT_FAILED']
            ]);

            #return redirect(config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($enquiryId)]));
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'BIKE','FAILURE'));
        }
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function rehitpdf($enquiryId, $policy_no, $pdf_url)
    {
        if ($policy_no && $enquiryId) 
        {
            $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
            

            try
            {
                if (empty($pdf_url) || $pdf_url == 'NA') {
                    return ['status' => false, 'msg' => 'Pdf service issue'];
                }
                $pdf_data = httpRequestNormal($pdf_url, 'GET', [], [], [], [], false)['response'];
                if(!checkValidPDFData($pdf_data))
                {
                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);
                        return [
                            'status' => 'false',
                            'msg' => "Pdf service issue"
                        ];
                }
                Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'universal_sompo/' . $proposal->user_proposal_id . '.pdf', $pdf_data);
                PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                ->update([
                    'pdf_url' => config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'universal_sompo/' . $proposal->user_proposal_id . '.pdf',
                ]);
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED']
                ]);


              return [
                    'status' => true,
                    'msg' => STAGE_NAMES['POLICY_PDF_GENERATED'],
                    'data' => [
                        'pdfUrl' => file_url(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'universal_sompo/' . $proposal->user_proposal_id . '.pdf'),
                    ]];
                  
            }
            catch (\Exception $e) 
            {
                updateJourneyStage([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);
                return [
                    'status' => false,
                    'msg' => $e->getMessage() . 'at Line no. -> ' . $e->getLine(),
                    'data' => [
                        'pdfUrl' => '',
                        
                    ]
                ];
            }
            
        } 
        else 
        {
            return [
                'status' => false,
                'message' => 'Please check enquiry id and policy number',
                'data'=> [ 'pdfUrl' => '']
            ];
        }
    }

    static public function generatePdf($request)
    {
        $user_product_journey_id = $enquiryId = customDecrypt($request->enquiryId);
        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin('policy_details as pd','pd.proposal_id','=','prr.user_proposal_id')
            ->join('user_proposal as up','up.user_product_journey_id','=','prr.user_product_journey_id')
            ->where('prr.user_product_journey_id',$user_product_journey_id)
            ->where(array('prr.active'=>1))
            ->select(
                'up.user_proposal_id', 'up.user_proposal_id','up.proposal_no','up.unique_proposal_id',
                'pd.policy_number','pd.pdf_url','pd.ic_pdf_url','prr.order_id','prr.response'
            )
            ->first();
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        if($policy_details == null)
        {
            $pdf_response_data = [
                'status' => false,
                'msg'    => 'Data Not Found'
            ];
            return response()->json($pdf_response_data);
        }

        if($policy_details->policy_number != '' && $policy_details->policy_number != 'NA' && $policy_details->pdf_url != '')
        {
            $pdf_response_data = [
                    'status' => false,
                    'msg'    => STAGE_NAMES['POLICY_PDF_GENERATED'],
                    'data'   => [
                        'policy_number' => $policy_details->policy_number,
                        'pdf_link'      => file_url($policy_details->pdf_url)
                    ]
                ];
            return  response()->json($pdf_response_data);
        }
          
        $payment_response = explode('|', ($policy_details->response ?? '')) ?? [];

        if (isset($payment_response[3])) {
            $policy_no = $payment_response[3];
            $pdf_url  = $payment_response[9];
        } else {
            $payment_response = json_decode(stripslashes($policy_details->response));
            $policy_no = $payment_response->PolicyNo;
            $pdf_url = $payment_response->DownloadURL;
        }

        if(empty($policy_details->response) || (empty($policy_no) || ($policy_no == 'NA') || empty($pdf_url)))
        {
            $output =self::update_pg_response(customEncrypt($user_product_journey_id));
            
            if($output['status'] == 'true')
            {
                if($output['policy_number'] != 'NA' && $output['policy_number'] != '')
                {
                   PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'policy_number'             => $output['policy_number'],
                        ]);
                    
                }
                if($output['DownloadURL'] != 'NA' && $output['DownloadURL'] != '')
                {
                   PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'ic_pdf_url'             => $output['DownloadURL'],
                        ]);
                   

                    $rehit_output = Self::rehitpdf($enquiryId, $output['policy_number'], $output['DownloadURL']);
                    if($rehit_output['status'] == 'true')
                    {
                        $pdf_response_data = [
                                'status' => true,
                                'msg' => 'success',
                                'data' => [
                                    'policy_number' => $output['policy_number'],
                                    'pdf_link'      => $rehit_output['data']['pdfUrl']
                                ]
                        ];
                    }
                    else
                    {
                        $pdf_response_data = [
                                'status' => false,
                                'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                'data' => [
                                    'policy_number' => $output['policy_number'],
                                    'pdf_link'      => ''
                                ]
                    ];
                    }      
                }

            }
            else
            {
                $pdf_response_data = [
                            'status' => false,
                            'msg' => $output['msg'],
                            'data' => [
                                'policy_number' => '',
                                'pdf_link'      => ''
                            ]
                ];

            }
            return response()->json($pdf_response_data);
        }
        else
        {
            if($policy_details->policy_number == '' || $policy_details->policy_number == 'NULL')
            {
                
                PolicyDetails::updateOrCreate(['proposal_id' => $proposal->user_proposal_id],
                    [
                        'policy_number'             => $policy_no,
                        'ic_pdf_url'          => $pdf_url,
                    ]
                );
                UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                        ->where('user_proposal_id',$proposal->user_proposal_id)
                        ->update([
                            'policy_no'             => $policy_no
                ]);

                $rehit_output = Self::rehitpdf($enquiryId, $policy_no, $pdf_url);
                if($rehit_output['status'] == 'true')
                {
                    $pdf_response_data = [
                            'status' => true,
                            'msg' => 'success',
                            'data' => [
                                'policy_number' => $policy_no,
                                'pdf_link'      => $rehit_output['data']['pdfUrl']
                            ]
                    ];
                }
                else
                {
                    $pdf_response_data = [
                            'status' => false,
                            'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                            'data' => [
                                'policy_number' => $policy_no,
                                'pdf_link'      => ''
                            ]
                ];
                }      

            }
            elseif($policy_details->pdf_url == '' && $policy_details->policy_number != '')
            {
                
                $rehit_output = Self::rehitpdf($enquiryId, $policy_no, $pdf_url);
                if($rehit_output['status'] == 'true')
                {
                    $pdf_response_data = [
                            'status' => true,
                            'msg' => 'success',
                            'data' => [
                                'policy_number' => $policy_no,
                                'pdf_link'      => $rehit_output['data']['pdfUrl']
                            ]
                    ];
                }
                else
                {
                    $pdf_response_data = [
                            'status' => false,
                            'msg' => $rehit_output['msg'] ?? STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                            'data' => [
                                'policy_number' => $policy_no,
                                'pdf_link'      => ''
                            ]
                ];
                }
              
                
            
            } 
            else 
            {
                $pdf_response_data = [
                    'status' => false,
                    'msg'    => STAGE_NAMES['POLICY_PDF_GENERATED'],
                    'data'   => []
                ];
            }

        }


        

        return response()->json($pdf_response_data);
    }

    static public function update_pg_response($enquiry_id)
    {
        $user_product_journey_id = customDecrypt($enquiry_id);
        // $proposal = UserProposal::where('user_product_journey_id',$user_product_journey_id)->select('proposal_no')->get();
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();

        $proposal_no = $proposal->proposal_no;
        if(empty($proposal_no))
        {
            return 
            [
                'status'=>'false',
                'msg'=> 'proposal number not found'
            ];
        }
        $url = config('constants.IcConstants.universal_sompo.END_POINT_URL_UNIVERSAL_SOMPO_GET_POLICY_STATUS') . '?PosPolicyNo=' . $proposal_no . '&WACode=' . config('constants.IcConstants.universal_sompo.AUTH_CODE_SOMPO_MOTOR');
        // $url = config('constants.IcConstants.universal_sompo.END_POINT_URL_UNIVERSAL_SOMPO_GET_POLICY_STATUS') . '?PosPolicyNo='.$proposal->proposal_no . '&FinalPremium=' . $proposal->final_payable_amount . '&Src=WA' . '&SubSrc=' . config('constants.IcConstants.universal_sompo.AUTH_CODE_SOMPO_MOTOR');

        $additionData = [
            'requestMethod' => 'get',
            'type' => 'proposal',
            'section' => 'bike',
            'method' => 'get payment status',
            'enquiryId' => $user_product_journey_id,
            'transaction_type' => 'proposal'
        ];

        $get_response = getWsData($url, '', 'universal_sompo', $additionData);
        $pg_response = $get_response['response'];
        $payment_response = json_decode($pg_response);
       
        if(isset($payment_response->PaymentStatus))
        {
            
            /* PaymentRequestResponse::where('proposal_no',  $proposal_no)
                ->where('active', 1)
                ->where('user_product_journey_id', $user_product_journey_id)
                ->update([
                    "response" =>$pg_response,
                ]); */
            if($payment_response->PaymentStatus == 'Payment done successfully.')
            {
                PaymentRequestResponse::where('proposal_no',  $proposal_no)
                ->where('active', 1)
                ->where('user_product_journey_id', $user_product_journey_id)
                ->update([
                    "response" =>$pg_response,
                    "status" => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);
                return 
                [
                    'status'=>'true',
                    'policy_number'=> isset($payment_response->PolicyNo) ? $payment_response->PolicyNo : '' ,
                    'DownloadURL'=> isset($payment_response->DownloadURL) ? $payment_response->DownloadURL : '',
                    'msg'=> 'Payment done successfully.'
                ];

            } elseif ($payment_response->PaymentStatus == 'Payment not done.') {
                PaymentRequestResponse::where('proposal_no',  $proposal_no)
                ->where('active', 1)
                ->where('user_product_journey_id', $user_product_journey_id)
                ->update([
                    "response" =>$pg_response,
                    "status" => STAGE_NAMES['PAYMENT_FAILED']
                ]);

                updateJourneyStage([
                    'user_product_journey_id' => $user_product_journey_id,
                    'stage' => STAGE_NAMES['PAYMENT_FAILED']
                ]);
                return [
                    'status' => false,
                    'policy_number' => $payment_response->PolicyNo ?? '' ,
                    'DownloadURL' => $payment_response->DownloadURL ?? '',
                    'msg' => $payment_response->PaymentStatus
                ];
            } else {
                 return 
                [
                    'status'=>'false',
                    'policy_number'=> isset($payment_response->PolicyNo) ? $payment_response->PolicyNo : '' ,
                    'DownloadURL'=> isset($payment_response->DownloadURL) ? $payment_response->DownloadURL : '',
                    'msg'=> $payment_response->PaymentStatus
                ];

            }
           

        }
        else
        {
            return 
            [
                'status'=>'false',
                'msg'=> 'There is problem with connecting insurance company, please try after 30 minutes.  If error persists, please contact support.'
            ];

        }
        
        
    }
}
