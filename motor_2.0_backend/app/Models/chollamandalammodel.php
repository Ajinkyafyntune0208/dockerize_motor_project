<?php

namespace App\Models;


//use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\PolicyDetails;
use App\Models\PaymentRequestResponse;
use App\Models\QuoteLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class chollamandalammodel extends Model
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
   public function token_generation($request_data){


        $token_param = [
            "grant_type"                => config('constants.IcConstants.cholla_madalam.CHOLLA_MANDALAM_MOTOR_GRANT_TYPE'),
            "username"                  => config('constants.IcConstants.cholla_madalam.CHOLLA_MANDALAM_MOTOR_USERNAME'),
            "password"                  => config('constants.IcConstants.cholla_madalam.CHOLLA_MANDALAM_MOTOR_PASSWORD'),
        ];


        $additional_data = [
            'requestMethod' => 'post',
            'Authorization' => config('constants.IcConstants.cholla_madalam.CHOLLA_MANDALAM_MOTOR_API_KEY'),
            'enquiryId' => $request_data['enquiryId'],
            'method' => 'Token Generation',
            'productName' => $request_data['productName'],
            'section' => $request_data['section'] ?? 'car',
            'transaction_type' => 'quote',
            'type'          => 'token'
        ];
        $get_response = getWsData(
            config('constants.IcConstants.cholla_madalam.END_POINT_URL_CHOLLA_MANDALAM_MOTOR_TOKEN'),
            $token_param ,
            'cholla_mandalam',
            $additional_data
        );
       $token_array = $get_response['response'];
       $token_response = json_decode($token_array, 1);

        if($token_response!=null){


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
    $refer_webservice = $request_data['quote_db_cache'];

        $user_code              = (($request_data['section'] == 'car') ? config('constants.IcConstants.cholla_madalam.CHOLLA_MANDALAM_MOTOR_USER_CODE') : config('constants.IcConstants.cholla_madalam.CHOLLA_MANDALAM_BIKE_USER_CODE'));
        $product_id             = $request_data['product_id'];
        $IMDShortcode_Dev       = (($request_data['section'] == 'car') ? config('constants.IcConstants.cholla_madalam.CHOLLA_MANDALAM_MOTOR_IMDSHORTCODE_DEV') : config('constants.IcConstants.cholla_madalam.CHOLLA_MANDALAM_BIKE_IMDSHORTCODE_DEV'));

        $idv_array = [
            'user_code'                 => $user_code,
            'product_id'                => $product_id,
            'IMDShortcode_Dev'          => $IMDShortcode_Dev,

            'make'                      => $request_data['make'],
            'model_variant'             => $request_data['model'],
            'frm_model_variant'         => $request_data['model'] . ' / ' . $request_data['fuel_type'] . ' / ' . $request_data['cc'] . ' CC',
            'frm_rto'                   => $rto_data['state'] . '-' . $rto_data['txt_rto_location_desc'] . '(' . $rto_data['state'] . ')',
            'ex_show_room'              => $request_data['showroom_price'],
            'mobile_no'                 => '8882515175',
            'date_of_reg'               => $this->get_excel_date($request_data['first_reg_date']),
            'sel_policy_type'           => (($request_data['idv_premium_type'] == 'own_damage' || $request_data['idv_premium_type'] == 'own_damage_breakin') ? 'Standalone OD' : ($request_data['business_type'] == 'newbusiness' ? 'Long Term' : 'Comprehensive')),
            'YOR'                       => $this->get_excel_date('01-01-'.date('Y', strtotime($request_data['first_reg_date']))),
            'rto_location_code'         => $request_data['rto_code'],
            'vehicle_model_code'        => $request_data['model_code'],
            "prev_exp_date_comp" => ((isset($request_data['new_vehicle']) && $request_data['new_vehicle']) ? null : $this->get_excel_date($request_data['previous_policy_expiry_date'])),
        ];

        if(isset($request_data['premium_type']) && $request_data['premium_type'] == 'O' || isset($request_data['idv_premium_type']) && ($request_data['idv_premium_type'] == 'own_damage' ||$request_data['idv_premium_type'] == 'own_damage_breakin')){
            $idv_array['tp_rsd']				= $request_data['tp_rsd'];
            $idv_array['tp_red']				= $request_data['tp_red'];
            $idv_array['od_rsd']				= $request_data['od_rsd'];
            $idv_array['od_red']				= $request_data['od_red'];
            $idv_array['rto_location_code']		= $request_data['rto_code'];
            $idv_array['vehicle_model_code']    = $request_data['model_code'];
            $idv_array['sel_idv']               = 'idv_4';
        }
        else
        {
            $idv_array['no_previous_insurer_chk'] 	= false;
            $idv_array['no_prev_ins'] 				= (($request_data['business_type'] == "rollover" || $request_data['business_type'] == "breakin") ? "No" : "Yes");
        }

        $url  = (($request_data['section'] == 'car') ? config('constants.IcConstants.cholla_madalam.END_POINT_URL_CHOLLA_MANDALAM_MOTOR_IDV') : config('constants.IcConstants.cholla_madalam.END_POINT_URL_CHOLLA_MANDALAM_BIKE_IDV'));



        $additional_data = [
            'requestMethod' => 'post',
            'Authorization' => $token,
            'enquiryId' => $request_data['enquiryId'],
            'method' => 'IDV Calculation',
            'productName' => $request_data['productName'],
            'section' => $request_data['section'],
            'type' => 'request',
            'transaction_type' => 'quote',
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . $token
            ]
        ];


        $checksum_data = checksum_encrypt($request_data);
        $is_data_exits_for_checksum = get_web_service_data_via_checksum($request_data['enquiryId'], 'cholla_mandalam', $checksum_data, 'CAR');
        $additional_data['checksum'] = $checksum_data;
        if ($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']) {
            $get_response = $is_data_exits_for_checksum;
        } else {
            $get_response = getWsData(
                $url,
                $idv_array,
                'cholla_mandalam',
                $additional_data
            );
        }

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
                    'message'       => 'Error while calculating IDV : '. ($idv_reponse['Message'] ?? $idv_reponse['message'])
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

    function motor_retry_pdf($transaction_no,$policy_id, $user_product_journey_id){

        DB::enableQueryLog();
        $proposal_details['unique_proposal_id'] = base64_decode($transaction_no);
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        $prods_details = getProductDataByIc($policy_id);
         $product_name = $prods_details->product_name;
        $company_name = $prods_details->company_name;

        $user_code                  = config('constants.IcConstants.cholla_madalam.CHOLLA_MANDALAM_MOTOR_USER_CODE');//'MIBLPB';
        //$product_id                 = 'M00000013';
        //$IMDShortcode_Dev           = 'MIBLPB';

        $request_data['quote']          = $proposal->user_product_journey_id;
        $request_data['company']        = $company_name;
        $request_data['product']        = $product_name;
        $request_data['section']        = 'car';
        $request_data['proposal_id']    = $proposal_details['unique_proposal_id'];
        $request_data['method']         = 'Token Generation - Payment';
        $request_data['enquiryId']      = $proposal->user_product_journey_id;
        $request_data['productName'] = $product_name;

        $token_response = chollamandalammodel::token_generation($request_data);

        if ($token_response['status'] == false) {
            return $token_response;
        }
        $token = $token_response['token'];
        $additional_details=json_decode($proposal->additional_details);

        $payent_info_array =
            [
                'user_code'             => $user_code,
                'payment_id'            => $proposal->proposal_no,
                'total_amount'          => ((config('constants.IcConstants.cholla_madalam.CHOLA_UAT_TOTAL_AMOUNT') == 'Y') ? 1 : $proposal->final_payable_amount),
                'billdesk_txn_date'     => $additional_details->billdesk_txn_date,
                'billdesk_txn_amount'   => $proposal->final_payable_amount,
                'billdesk_txn_ref_no'   => $additional_details->billdesk_txn_ref_no,
            ];


        $additional_data = [
            'requestMethod' => 'post',
            'Authorization' => $token,
            'enquiryId' => $proposal->user_product_journey_id,
            'method' => 'Payment Validation - Payment',
            'section' => 'car',
            'type' => 'request',
            'transaction_type' => 'proposal'
        ];
        sleep(5);
        $get_response = getWsData(
            config('constants.IcConstants.cholla_madalam.END_POINT_URL_CHOLLA_MANDALAM_MOTOR_PAYMENT'),
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

//                $quries = DB::getQueryLog();
//                 print_r($quries);
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
                    'section' => 'car',
                    'method'        => 'Policy Download - Payment',
                    'type'          => 'get_request',
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token
                    ],
                    'transaction_type' => 'proposal'
                ];
                $url = config('constants.IcConstants.cholla_madalam.END_POINT_URL_CHOLLA_MANDALAM_MOTOR_DOWNLOAD_POLICY_URL') .'?'."policy_id=".$payinfo_output->policy_id."&user_code=".$user_code;
//            http_build_query($policy_param);

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

                    if(!empty($content) && is_string($content) && strpos($content, '%PDF') === 0)
                    {
                        $pdf_final_data = Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'cholla_mandalam/'. md5($proposal->user_proposal_id). '.pdf', $content);

                        if ($pdf_final_data == true) {
                            updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED']
                            ]);
                            $policy_result=PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update(
                                [

                                    'policy_number' =>   $proposal_data['policy_no'],
                                    'ic_pdf_url' => $pdf_data->Data,
                                    'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL').'cholla_mandalam/'.md5($proposal->user_proposal_id).'.pdf',
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

    function bike_retry_pdf($transaction_no,$policy_id, $user_product_journey_id){
        DB::enableQueryLog();
        $proposal_details['unique_proposal_id'] = base64_decode($transaction_no);
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        $prods_details = getProductDataByIc($policy_id);
        $product_name = $prods_details->product_name;
        $company_name = $prods_details->company_name;

        $user_code                  = config('constants.IcConstants.cholla_madalam.CHOLLA_MANDALAM_BIKE_USER_CODE');//'MIBLPB';
        //$product_id                 = 'M00000013';
        //$IMDShortcode_Dev           = 'MIBLPB';

         $request_data['quote']          = $proposal->user_product_journey_id;
        $request_data['company']        = $company_name;
        $request_data['product']        = $product_name;
        $request_data['section']        = 'bike';
        $request_data['proposal_id']    = $proposal_details['unique_proposal_id'];
        $request_data['method']         = 'Token Generation - Payment';
        $request_data['enquiryId']      = $proposal->user_product_journey_id;
        $request_data['productName'] = $product_name;

        $token_response = chollamandalammodel::token_generation($request_data);

        if ($token_response['status'] == false) {
            return $token_response;
        }
        $token = $token_response['token'];
        $additional_details=json_decode($proposal->additional_details);

        $payent_info_array =
            [
                'user_code'             => $user_code,
                'payment_id'            => $proposal->proposal_no,
                'total_amount'          => $proposal->final_payable_amount,
                'billdesk_txn_date'     => $additional_details->billdesk_txn_date,
                'billdesk_txn_amount'   => $proposal->final_payable_amount,
                'billdesk_txn_ref_no'   => $additional_details->billdesk_txn_ref_no,
            ];


        $additional_data = [
            'requestMethod' => 'post',
            'Authorization' => $token,
            'enquiryId' => $proposal->user_product_journey_id,
            'method' => 'Payment Validation - Payment',
            'section' => 'bike',
            'type'          => 'request',
            'transaction_type' => 'proposal'
        ];



        $get_response = getWsData(
            config('constants.IcConstants.cholla_madalam.END_POINT_URL_CHOLLA_MANDALAM_BIKE_PAYMENT'),
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
                        'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                    ]
                );
            $data['user_product_journey_id'] = $proposal->user_product_journey_id;
            $data['ic_id'] = $proposal->ic_id;
            $data['stage'] = STAGE_NAMES['PAYMENT_SUCCESS'];
            updateJourneyStage($data);
            return [
                'status'=>false,
                'message'=>STAGE_NAMES['PAYMENT_SUCCESS']
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

            $updatePayment=PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
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

//                $quries = DB::getQueryLog();
//                 print_r($quries);
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
                'section' => 'bike',
                'method'        => 'Policy Download - Payment pdf',
                'type'          => 'get_request',
                'transaction_type' => 'proposal'
            ];
            $url = config('constants.IcConstants.cholla_madalam.END_POINT_URL_CHOLLA_MANDALAM_BIKE_DOWNLOAD_POLICY_URL') .'?'."policy_id=".$payinfo_output->policy_id."&user_code=".$user_code;
//            http_build_query($policy_param);

            $get_response = getWsData(
                $url,
                $policy_param ,
                'cholla_mandalam',
                $additional_payment_data
            );


            $pdf_data = $get_response['response'];
            $pdf_data=json_decode($pdf_data);


            $data   = [];

            if (isset($pdf_data->Data)) {
                /* $client = new \GuzzleHttp\Client();
                $res = $client->get($pdf_data->Data);
                $content = (string) $res->getBody(); 
                if(!empty($content))
                {*/
                    // $pdf_final_data = Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'cholla_mandalam/'. md5($proposal->user_proposal_id). '.pdf', file_get_contents($pdf_data->Data));

                    $pdfGenerationResponse = httpRequestNormal($pdf_data->Data, 'GET', [], [], [], [], false)['response'];

                    $pdfName = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'cholla_mandalam/'. md5($proposal->user_proposal_id). '.pdf';

                    $pdf_final_data = Storage::put($pdfName, $pdfGenerationResponse);

                    if ($pdf_final_data == true) {
                        updateJourneyStage([
                            'user_product_journey_id' => $proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED']
                        ]);
                        $policy_result=PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update(
                            [

                                'policy_number' =>   $proposal_data['policy_no'],
                                'ic_pdf_url' => $pdf_data->Data,
                                'pdf_url' => config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'cholla_mandalam/'. md5($proposal->user_proposal_id). '.pdf',
                                'status' =>  STAGE_NAMES['POLICY_ISSUED']
                            ]
                        );
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
               /*  } */
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
            Config('constants.IcConstants.cholla_madalam.QUERY_API_MERCHANT_ID_CHOLLA_MANDALAM'),
            $transactiondata->order_id,
            date('Ymdhis'),
        ];

        $query_api = implode('|', $query_api_array);
        $checksum = strtoupper(hash_hmac('sha256', $query_api, config('constants.IcConstants.cholla_madalam.PAYMENT_CHECKSUM_CHOLLA_MANDALAM')));

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
            Config('constants.IcConstants.cholla_madalam.QUERY_API_URL_CHOLLA_MANDALAM'),
            $query_api_request,
            'cholla_mandalam',
            $additional_payment_data
        );
        $query_api_data = $get_response['response'];

//        $query_api_data='CHOLATEST|COMPAREPOLICY20211101163575007315|WIC40383801805|130570527871|00000001.00|IC4|NA|10|INR|DIRECT|NA|NA|0.00|01-11-2021 12:30:48|0300|NA|PY000000020137|MH-01-NH-2121|8779811756|vnathn1253@gmail.com|DSFDSFDSFSDFSD|Vishwanath Nijampurkar|NA|NA|Y|9C3C60AF5EAF673124E374ED5558D34B34C9422DDD8B5F5782F63840EDF676CD PGIBL1000 B101 00003051 5781';

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


                        if($section == 'car')
                        {
                            $pdf_data = $this->motor_retry_pdf(base64_encode($incomplete_transactions->order_id),$quote_data->master_policy_id, $proposal->user_product_journey_id);

                            if ($pdf_data['status'] || $pdf_data['status']== true) {
                                $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                                $data['ic_id'] = $proposal->ic_id;
                                $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                                updateJourneyStage($data);
                                PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([

                                    'policy_number' => $pdf_data['policy_no'],
                                    'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL').'cholla_mandalam/'.md5($proposal->user_proposal_id).'.pdf',
                                    'policy_start_date' => $proposal->policy_start_date,
                                    'status' =>  'Success'

                                ]);
                                $return_data= [
                                    'status' => true,
                                    'msg' => 'Success',
                                    'data' => [
                                        'policy_number' => $pdf_data['policy_no'],
                                        'pdf_link'      => file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL').'cholla_mandalam/'.md5($proposal->user_proposal_id).'.pdf')
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

                            if ($pdf_data['status'] || $pdf_data['status']==true) {
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
