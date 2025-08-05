<?php

namespace App\Models;


//use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\PolicyDetails;
use App\Models\PaymentRequestResponse;
use App\Models\QuoteLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class ChollaMandalamCvModel extends Model
{
    public function get_excel_date($date){
        $output=null;
        if($date!=null){
            $datetime   = strtotime($date);
            $unixdate   = 25569;
            $day        = 86400;
            $output     = round($unixdate + (($datetime + date('Z', $datetime)) / $day));
        }

        return $output;
    }
   public function token_generation($request_data)
   {
        $token_param = [
            "grant_type"                => config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_GRANT_TYPE'),
            "username"                  => config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_USERNAME'),
            "password"                  => config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_PASSWORD'),
        ];

        $additional_data = [
            'requestMethod' => 'post',
            'Authorization' => config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_API_KEY'),
            'enquiryId' => $request_data['enquiryId'],
            'method' => 'Token Generation',
            'section' => $request_data['section'] ?? 'CV',
            'transaction_type' => 'quote',
            'productName' => $request_data['section'] ,
            'type'          => 'token'
        ];

        $get_response = getWsData(
            config('constants.IcConstants.cholla_madalam.cv.END_POINT_URL_CHOLLA_MANDALAM_CV_TOKEN'),
            $token_param ,
            'cholla_mandalam',
            $additional_data
        );
       $token_array = $get_response['response'];
       $token_response = json_decode($token_array, 1);

        if($token_response!=null)
        {
            if(isset($token_response['access_token']) && $token_response['access_token'] != ''){
                return [
                    'status'     => true,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'token'      => $token_response['access_token']
                ];
            }else{
                return [
                    'status'    => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message'      => 'Error while generating token : '.$token_response['error_description']
                ];
            }
        }else{
            return [
                'status'    => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message'      => 'Insurer Not found'
            ];
        }
    }

 public function idv_calculation($rto_data,$request_data, $token){

        $user_code              = config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_USER_CODE');
        $product_id             = config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_PRODUCT_ID');
        $IMDShortcode_Dev       = config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_IMDSHORTCODE_DEV');
        switch ($request_data['business_type']) {
            case 'rollover':
                $business_type = 'Roll Over';
                break;
            case 'newbusiness':
                $business_type = 'New Business';
                break;
            default:
                $business_type = $request_data['business_type'];
                break;
        }
        $idv_array = [
            'user_code'                 => $user_code,
            'IMDShortcode_Dev'          => $IMDShortcode_Dev,
            "app_product_name"          => "gccv",//default
            "business_transaction_type" => $business_type,
            'product_name1'             => ($request_data['idv_premium_type'] == 'own_damage' ? 'Standalone OD' : ($request_data['business_type'] == 'newbusiness' ? 'Long Term' : 'Comprehensive')),
            "vehicle_class_dev"         => $request_data['sub_Class'],
            'make'                      => $request_data['make'],
            "vehicle_model_code"        => $request_data['model_code'],
            "DOR"                       => $request_data['Dor'], // user input
            "rto_location_code"         => $rto_data['txt_rto_location_code'], // master
            "noprev_insurance"          => "No",
            "prev_policy_exp"           => $request_data['prevpolicyexp'], //user input
            "product_id"                => $product_id,
        ];

        $url  = config('constants.IcConstants.cholla_madalam.cv.END_POINT_URL_CHOLLA_MANDALAM_MOTOR_CV_IDV');
        $additional_data = [
            'requestMethod' => 'post',
            'Authorization' => $token,
            'enquiryId' => $request_data['enquiryId'],
            'method' => 'IDV Calculation',
            'section' => $request_data['section'],
            'type' => 'request',
            'transaction_type' => 'quote',
            'productName' => $request_data['section'],
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ];
        $get_response = getWsData(
            $url,
            $idv_array,
            'cholla_mandalam',
            $additional_data

        );
     $idv_reponse = $get_response['response'];
     $idv_reponse = json_decode($idv_reponse, 1);

        if($idv_reponse!=null){


            if(isset($idv_reponse['Status']) && $idv_reponse['Status'] == 'success'){
                return [
                    'status'     => true,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'idv_range'      => $idv_reponse['Data']
                ];
            }else{
                return [
                    'status'        => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message'       => 'Error while calculating IDV : '.$idv_reponse['Message']
                ];
            }
        }else{
            return [
                'status'    => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message'      => 'Insurer Not found'
            ];
        }
    }

    function gcv_retry_pdf($transaction_no,$policy_id, $user_product_journey_id){

        DB::enableQueryLog();
        $proposal_details['unique_proposal_id'] = base64_decode($transaction_no);
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        $prods_details = getProductDataByIc($policy_id);
         $product_name = $prods_details->product_name;
        $company_name = $prods_details->company_name;

        $user_code                  = config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_USER_CODE');
        //$product_id                 = 'M00000013';
        //$IMDShortcode_Dev           = 'MIBLPB';

        $request_data['quote']          = $proposal->user_product_journey_id;
        $request_data['company']        = $company_name;
        $request_data['product']        = $product_name;
        $request_data['section']        = 'GCV';
        $request_data['proposal_id']    = $proposal_details['unique_proposal_id'];
        $request_data['method']         = 'Token Generation - Payment';
        $request_data['enquiryId']      = $proposal->user_product_journey_id;

        $token_response = ChollaMandalamCvModel::token_generation($request_data);

        if ($token_response['status'] == false) {
            return $token_response;
        }
        $token = $token_response['token'];
        $additional_details=json_decode($proposal->additional_details);

        $payent_info_array =
            [
                'user_code'             => $user_code,
                'payment_id'            => $proposal->proposal_no,
                'total_amount'          => ((config('constants.IcConstants.cholla_madalam.cv.CHOLA_UAT_TOTAL_AMOUNT') == 'Y') ? 1 : $proposal->final_payable_amount),
                'billdesk_txn_date'     => $additional_details->billdesk_txn_date,
                'billdesk_txn_amount'   => $proposal->final_payable_amount,
                'billdesk_txn_ref_no'   => $additional_details->billdesk_txn_ref_no,
            ];


        $additional_data = [
            'requestMethod' => 'post',
            'Authorization' => $token,
            'enquiryId' => $proposal->user_product_journey_id,
            'method' => 'Payment Validation - Payment',
            'section' => 'CV',
            'type' => 'request',
            'transaction_type' => 'proposal'
        ];
        sleep(5);
        $get_response = getWsData(
            config('constants.IcConstants.cholla_madalam.cv.END_POINT_URL_CHOLLA_MANDALAM_CV_PAYMENT'),
            $payent_info_array,
            'cholla_mandalam',
            $additional_data

        );
        $payent_info_data = $get_response['response'];
        // END LINKING PAYMENT AND PROPOSAL
        $payinfo_output = (object)json_decode($payent_info_data, true);

            if(!isset($payinfo_output->Status) || $payinfo_output->Status != 'success'){

                PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->where('active', 1)
                    ->update(
                        [

                            'updated_at' => date('Y-m-d H:i:s'),
                            'status' => STAGE_NAMES['PAYMENT_FAILED']
                        ]
                    );
                $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                $data['ic_id'] = $proposal->ic_id;
                $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
                updateJourneyStage($data);
                return [
                    'status'=>false,
                    'message'=>STAGE_NAMES['PAYMENT_FAILED']
                ];

            }
            else{
                     $policy_result=PolicyDetails::updateorCreate(
                    ['proposal_id' => $proposal->user_proposal_id],
                    [

                        'policy_start_date'=>$proposal->policy_start_date,
                        'idv'=>$proposal->idv,
                        'ncb'=>$proposal->ncb_discount,
                        'premium'=>$proposal->final_payable_amount

                    ]
                );

                $payinfo_output = (object)$payinfo_output->Data;

              $updatePayment= PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                  ->where('active', 1)
                    ->update(
                        [

                            'updated_at' => date('Y-m-d H:i:s'),
                            'status' =>  STAGE_NAMES['PAYMENT_SUCCESS']

                ]);
                $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                $data['ic_id'] = $proposal->ic_id;
                $data['stage'] = STAGE_NAMES['PAYMENT_SUCCESS'];
                updateJourneyStage($data);

                $proposal_data['policy_no'] = $payinfo_output->tp_policy_no;

                $additional_details->policy_id=$payinfo_output->policy_id;
                $additional_details->second_policy_no=$payinfo_output->policy_no;
                $additional_details=json_encode($additional_details);
                $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'policy_no'             => $payinfo_output->tp_policy_no,
                        'additional_details'             => $additional_details,
                    ]);


                $policy_param = [
                    "policy_id"     => $payinfo_output->policy_id,
                    "user_code"     => $user_code,
                ];
                $additional_payment_data = [
                    'requestMethod' => 'get',
                    'Authorization' => $token,
                    'proposal_id'   => $request_data['proposal_id'],
                    'enquiryId' => $proposal->user_product_journey_id,
                    'section' => 'CV',
                    'method'        => 'Policy Download - Payment',
                    'type'          => 'get_request',
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token
                    ],
                    'transaction_type' => 'proposal'
                ];
                $url = config('constants.IcConstants.cholla_madalam.cv.END_POINT_URL_CHOLLA_MANDALAM_CV_DOWNLOAD_POLICY_URL') .'?'."policy_id=".$payinfo_output->policy_id."&user_code=".$user_code;

                $get_response = getWsData(
                    $url,
                    $policy_param ,
                    'cholla_mandalam',
                    $additional_payment_data
                );

                $pdf_data = $get_response["response"];
                $pdf_data=json_decode($pdf_data);


                $data   = [];

                    if (isset($pdf_data->Data)) {

                        // $client = new \GuzzleHttp\Client();
                        // $res = $client->get($pdf_data->Data);
                        // $content = (string) $res->getBody();
                    $content = httpRequestNormal($pdf_data->Data, 'GET', [], [], [], [], false)['response'];

                    if(!empty($content))
                    {
                        $pdf_final_data = Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'cholla_mandalam/'. md5($proposal->user_proposal_id). '.pdf', $content);

                        if ($pdf_final_data == true) {
                            updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED']
                            ]);
                            $policy_result=PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update(
                                [

                                    'policy_number' =>   $proposal_data['policy_no'],
                                    'ic_pdf_url' => $pdf_data->Data,
                                    'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL').'cholla_mandalam/'.md5($proposal->user_proposal_id).'.pdf',
                                    'status' =>  STAGE_NAMES['POLICY_ISSUED']
                                ]
                            );
                        }
                     } else {
                            updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                            ]);
                            $policy_result=PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update(
                                [
                                    'policy_number' =>   $proposal_data['policy_no'],
                                    'ic_pdf_url' => $pdf_data->Data,
                                   'status' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                ]
                            );

                            return [
                                'status' => false,
                                'message' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                'policy_no' => $proposal_data['policy_no'],

                            ];
                        }
                    } else {
                        updateJourneyStage([
                            'user_product_journey_id' => $proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);
                        $policy_result=PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update(
                            [
                                'policy_number' =>  $proposal_data['policy_no'],
                                'ic_pdf_url' => $pdf_data->Data,
                                'status' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                            ]
                        );
                        return [
                            'status' => false,
                            'message' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                            'policy_no' => $proposal_data['policy_no'],

                        ];
                    }




                return [
                    'status' => true,
                    'message' => 'PDF Generated Successfully',
                   'policy_no' => $proposal_data['policy_no'],

                ];
            }
        }


    function check_payment_status($transactiondata, $request_data){

        $query_api_array = [
            '0122',
            Config('constants.IcConstants.cholla_madalam.cv.QUERY_API_MERCHANT_ID_CHOLLA_MANDALAM'),
            $transactiondata->order_id,
            date('Ymdhis'),
        ];

        $query_api = implode('|', $query_api_array);
        $checksum = strtoupper(hash_hmac('sha256', $query_api, config('constants.IcConstants.cholla_madalam.cv.PAYMENT_CHECKSUM_CHOLLA_MANDALAM')));

        $new_string = $query_api.'|'.$checksum;
        $query_api_request = [
            'msg' => $new_string
        ];

        $additional_payment_data = [
            'requestMethod' => 'get',
            'Authorization'=>'',
            'proposal_id'   => $request_data['proposal_id'],
            'enquiryId' => $request_data['enquiryId'],
            'section' => $request_data['section'],
            'method'        => 'Query API - Payment Status',
            'type'          => 'Query API',
            'transaction_type' => 'proposal'
        ];

        $get_response = getWsData(
            Config('constants.IcConstants.cholla_madalam.cv.QUERY_API_URL_CHOLLA_MANDALAM'),
            $query_api_request,
            'cholla_mandalam',
            $additional_payment_data
        );
        $query_api_data = $get_response['response'];

        if(!empty($query_api_data) && $query_api_data!='{}')
        {
            $query_api_response = explode('|', $query_api_data);
            $query_api_data = implode('|',$query_api_response);

            if($query_api_response[14] == '0300'){
                return [
                    'status'    => true,
                    'message'   => $query_api_data
                ];
            }else{
                return [
                    'status'    => false,
                    'message'   => $query_api_response[25]
                ];
            }
        }else{

            return [
                'status'    => false,
                'message'   => 'No response Form Query API service'
            ];
        }

    }

    function update_transaction_summary($request,$section)
    {
        DB::enableQueryLog();
        $return_data    = array();

        $user_product_journey_id = customDecrypt($request->enquiryId);
            $incomplete_transactions =PaymentRequestResponse::leftjoin('policy_details as pd','pd.proposal_id','=','payment_request_response.user_proposal_id')
            ->join('user_proposal as up','up.user_product_journey_id','=','payment_request_response.user_product_journey_id')
            ->where(array('payment_request_response.user_product_journey_id'=>$user_product_journey_id,'payment_request_response.active'=>1))
            ->whereIn('payment_request_response.status',[ STAGE_NAMES['PAYMENT_INITIATED'],STAGE_NAMES['PAYMENT_FAILED']])
            ->select(
                'up.user_proposal_id', 'up.user_proposal_id','up.proposal_no','up.unique_proposal_id',
                'pd.policy_number','pd.pdf_url','pd.ic_pdf_url','payment_request_response.order_id','payment_request_response.user_product_journey_id'
            )
            ->first();
        $query = DB::getQueryLog();

            if(!empty($incomplete_transactions)){
               $quote_data = QuoteLog::join('master_company as mc','mc.company_id','=','quote_log.ic_id')
                    ->join('master_product as mp','mp.master_policy_id','=','quote_log.master_policy_id')
                    ->where('quote_log.user_product_journey_id',$user_product_journey_id)
                    ->select(
                        'quote_log.user_product_journey_id','quote_log.product_sub_type_id','quote_log.ic_id','quote_log.master_policy_id',
                        'mc.company_name','mc.company_alias',
                        'mp.product_name'
                    )
                    ->first();
                $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();

                $product_name = $quote_data->product_name;
                $company_name = $quote_data->company_name;

                $request_data['proposal_id']    = $incomplete_transactions->user_proposal_id;
                $request_data['quote']          = $user_product_journey_id;
                $request_data['company']        = $company_name;
                $request_data['product']        = $product_name;
                $request_data['enquiryId'] =$user_product_journey_id;
                $request_data['section']=$section;

                try{

                    $check_payment_status   = $this->check_payment_status($incomplete_transactions, $request_data);
                    if(isset($check_payment_status['status']) && $check_payment_status['status'] != true){
                        $return_data_arr['msg']    = $check_payment_status['message'];
                        $return_data_arr['status']    = $check_payment_status['status'];
                        $return_data  =$return_data_arr;
                        PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                            ->where('user_proposal_id', $proposal->user_proposal_id)
                            ->where('active', 1)
                            ->update(
                                [
                                    'response' => $check_payment_status['message'],
                                    'updated_at' => date('Y-m-d H:i:s'),
                                    'status' => STAGE_NAMES['PAYMENT_FAILED']
                                ]
                            );
                    }else{
                        $pg_resp_str = explode('|', $check_payment_status['message']);

                        PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                            ->where('user_proposal_id', $proposal->user_proposal_id)
                            ->where('active', 1)
                            ->update(
                                [
                                    'response' => $check_payment_status['message'],
                                    'updated_at' => date('Y-m-d H:i:s'),
                                    'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                                ]
                            );

                        $policy_result=PolicyDetails::updateorCreate(
                            ['proposal_id' => $proposal->user_proposal_id],
                            [
                                'proposal_id'=>$proposal->user_proposal_id,
                                'policy_start_date'=>$proposal->policy_start_date,
                                'idv'=>$proposal->idv,
                                'ncb'=>$proposal->ncb_discount,
                                'premium'=>$proposal->final_payable_amount

                            ]
                        );

                        $additional_details=json_decode($proposal->additional_details);
                        $additional_details->billdesk_txn_date=$pg_resp_str[13];
                        $additional_details->billdesk_txn_ref_no=$pg_resp_str[2];
                        $additional_details=json_encode($additional_details);


                        $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                            ->where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'additional_details' => $additional_details,

                            ]);


                        if($section == 'gcv')
                        {
                            $pdf_data = $this->gcv_retry_pdf(base64_encode($incomplete_transactions->order_id),$quote_data->master_policy_id, $proposal->user_product_journey_id);

                            if ($pdf_data['status'] || $pdf_data['status']== true) {
                                $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                                $data['ic_id'] = $proposal->ic_id;
                                $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                                updateJourneyStage($data);
                                PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([

                                    'policy_number' => $pdf_data['policy_no'],
                                    'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL').'cholla_mandalam/'.md5($proposal->user_proposal_id).'.pdf',
                                    'policy_start_date' => $proposal->policy_start_date,
                                    'status' =>  'Success'

                                ]);
                                $return_data= [
                                    'status' => true,
                                    'msg' => 'Success',
                                    'data' => [
                                        'policy_number' => $pdf_data['policy_no'],
                                        'pdf_link'      => file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL').'cholla_mandalam/'.md5($proposal->user_proposal_id).'.pdf')
                                    ]
                                ];

                            } else {
                                $return_data= [
                                    'status' => false,
                                    'msg'    => $pdf_data['message'],
                                    'data'   => []
                                ];
                            }
                        }
                        else
                        {
                            $pdf_data = $this->bike_retry_pdf(base64_encode($incomplete_transactions->order_id), $quote_data->master_policy_id, $proposal->user_product_journey_id);

                            if ($pdf_data['status'] || $pdf_data['status']== true) {
                                $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                                $data['ic_id'] = $proposal->ic_id;
                                $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                                updateJourneyStage($data);
                                PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([

                                    'policy_number' => $pdf_data['policy_no'],
                                    'pdf_url' => config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'cholla_mandalam/'.md5($proposal->user_proposal_id).'.pdf',
                                    'policy_start_date' => $proposal->policy_start_date,
                                    'status' =>  'Success'

                                ]);

                                $return_data= [
                                    'status' => true,
                                    'msg' => 'Success',
                                    'data' => [
                                        'policy_number' => $pdf_data['policy_no'],
                                        'pdf_link'      => file_url(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'cholla_mandalam/'.md5($proposal->user_proposal_id).'.pdf')
                                    ]
                                ];

                            } else {

                                $return_data= [
                                    "status"=>false,
                                    "message"=>'Retry pdf Failed'
                                ];
                            }
                        }
                    }


                }
                catch(\Exception $e)
                {
                    $return_data = [
                        "status"=>false,
                        'enquiryId'  => $request->enquiryId,
                        'no'     => $incomplete_transactions->order_id,
                        'message'    => $e->getMessage().' '.$e->getLine().' '.$e->getFile(),

                    ];
                }
            } else {
                $return_data = [
                    "status"=>false,
                    'enquiryId'  => $request->enquiryId,
                    'message'    => 'No Incomplete Transaction Found',

                ];
            }

        return $return_data;
    }
}
