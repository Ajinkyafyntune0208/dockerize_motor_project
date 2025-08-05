<?php

namespace App\Jobs;

use App\Models\UserProposal;
use App\Models\QuoteLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

require_once app_path().'/Helpers/CvWebServiceHelper.php';
require_once app_path() . '/Helpers/CarWebServiceHelper.php';


class IciciLombardBrekinStatusUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        // icici lombard brekin status update
        $corelation_id = getUUID();
        $section = 'taxi';

        $additionData = [
            'requestMethod' => 'post',
            'type' => 'tokenGeneration',
            'section' =>  $section ,
            'enquiryId' => null,
            'transaction_type' => 'proposal'
        ];

        $username = !empty(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME')) ? config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME') : config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_MOTOR');
        $password = !empty(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD')) ? config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD') : config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_MOTOR');
        $client_id = !empty(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID')) ? config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID') : config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_MOTOR');
        $client_secret = !empty(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET')) ? config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET') : config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_MOTOR');

        $tokenParam = [
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'scope' => 'esbmotor'
        ];

        $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL'), http_build_query($tokenParam), 'icici_lombard', $additionData);

        $token = $get_response['response'];
        if (!empty($token))
        {
            $token = json_decode($token, true);
            if(!isset($token['access_token']))
            {
                return false;
            }
            $access_token = $token['access_token'];

            $additionData['requestMethod'] = 'get';
            $additionData['type'] = 'brekinInspectionStatus';
            $additionData['token'] =  $access_token;

            $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CHECK_INSPECTION_STATUS').'?count=5', '', 'icici_lombard', $additionData);
            $inspectionStatusResponse = $get_response['response'];
            if (!empty($inspectionStatusResponse))
            {
                $inspection_status_response = json_decode($inspectionStatusResponse, true);
                $brekin_status = $inspection_status_response['breakinstatus'] ?? NULL;
                
                if ($brekin_status != null)
                {
                    foreach ($brekin_status as $key => $value)
                    {                        
                        $brknIdd = trim($value['breakInInsuranceID']);
                        
                        $brekin_recommeded_id = DB::table('cv_breakin_status')
                            ->where('breakin_number', $brknIdd)
                            ->where('ic_id', 40)
                            ->first('*');
                        if($brekin_recommeded_id)
                        {
                            $inspectionStatus = $brekin_recommeded_id->breakin_status;
                            if($value['inspectionStatus'] == 'Recommended')
                            {
                              $inspectionStatus = STAGE_NAMES['INSPECTION_APPROVED'];  
                            }
                            else if($value['inspectionStatus'] == 'Rejected' || $value['inspectionStatus'] == 'Reject')
                            {
                               $inspectionStatus = STAGE_NAMES['INSPECTION_REJECTED']; 
                            }
                            
                            DB::table('cv_breakin_status')
                            ->where('breakin_number', $brknIdd)
                            ->where('ic_id', 40)
                            ->update([
                                'breakin_status' => $inspectionStatus,//$value['inspectionStatus']
                            ]);
                            
                            $brekin_recommeded_id = DB::table('cv_breakin_status')
                            ->where('breakin_number', $brknIdd)
                            ->where('ic_id', 40)
                            ->first('*');
                            
                            $proposalDetail = UserProposal::find($brekin_recommeded_id->user_proposal_id);

                            $master_policy_id = QuoteLog::where('user_product_journey_id', $proposalDetail->user_product_journey_id)
                                ->first();

                            $productData = getProductDataByIc($master_policy_id->master_policy_id);

                            switch ($productData->product_sub_type_id)
                            {
                                case '1'://car
                                    $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_BREAKIN');

                                    $product_code = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_MOTOR');

                                    $section = 'car';
                                    break;

                                case '6'://taxi
                                    $deal_id = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_BREAKIN');
                                    $product_code = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE');
                                    /* $deal_id = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_BREAKIN_DEAL_ID');
                                    $product_code = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PCV_PRODUCT_CODE'); */
                                    $section = 'taxi';
                                    break;

                                case '9':
                                case '13':
                                case '14':
                                case '14':
                                    $deal_id = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV_BREAKIN');
                                    $product_code = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_GCV_PRODUCT_CODE');
                                    $section = 'GCV Breakin';
                                    break;

                            }


                            // if($brekin_recommeded_id->breakin_status_final != STAGE_NAMES['PENDING_FROM_IC'])
                            // {
                            //     echo 'Records already updated from IC for generated breakin ids';
                            //     return [
                            //         'status' => false,
                            //         'message' => 'Record already updated'
                            //     ];
                            // }

                            //if ($brekin_recommeded_id->breakin_status == 'Recommended')
                            if ($brekin_recommeded_id->breakin_status == STAGE_NAMES['INSPECTION_APPROVED'])
                            {
                                $inspection_data = [
                                    'InspectionId' => $brekin_recommeded_id->breakin_number,
                                    'ReferenceDate' => date('d/m/Y', strtotime($brekin_recommeded_id->created_at)),
                                    'CorrelationId' => $corelation_id,
                                    'InspectionStatus' => "OK",
                                    'ReferenceNo' => $proposalDetail->proposal_no
                                ];

                                $additionData['requestMethod'] = 'post';
                                $additionData['type'] = 'brekinInspectionStatus';
                                $additionData['section'] = $section;

                                $is_pos = 'N';
                                $is_icici_pos_disabled_renewbuy = config('constants.motorConstant.IS_ICICI_POS_DISABLED_RENEWBUY');
                                $is_pos_enabled = ($is_icici_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED');
                                $pos_testing_mode = ($is_icici_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE');
                                $pos_data = DB::table('cv_agent_mappings')
                                    ->where('user_product_journey_id',$proposalDetail->user_product_journey_id)
                                    ->where('user_proposal_id',$brekin_recommeded_id->user_proposal_id)
                                    ->where('seller_type','P')
                                    ->first();
                                if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
                                {
                                    if($pos_data)
                                    {
                                        $is_pos = 'Y';
                                        $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER');
                                        $CertificateNumber = $pos_data->unique_number;#$pos_data->user_name;
                                        $PanCardNo = $pos_data->pan_no;
                                        $AadhaarNo = $pos_data->aadhar_no;
                                    }
                                    $ProductCode = $product_code;
                                }
                                elseif($pos_testing_mode === 'Y')
                                {
                                    $is_pos = 'Y';
                                    $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                                    $CertificateNumber = 'TMI0001';
                                    $PanCardNo = 'ABGTY8890Z';
                                    $AadhaarNo = '569278616999';
                                    $ProductCode = $product_code;
                                }
                                else
                                {

                                    $inspection_data['DealNo'] = $deal_id;
                                }


                                if($is_pos == 'Y')
                                {
                                    if(isset($inspection_data['DealNo']))
                                    {
                                        unset($inspection_data['DealNo']);
                                    }
                                }
                                else
                                {
                                    if(!isset($inspection_data['DealNo']))
                                    {
                                       $inspection_data['DealNo'] = $deal_id;
                                    }
                                }

                                if($is_pos == 'Y')
                                {
                                    $pos_details = [
                                        'pos_details' => [
                                            'IRDALicenceNumber' => $IRDALicenceNumber,
                                            'CertificateNumber' => $CertificateNumber,
                                            'PanCardNo'         => $PanCardNo,
                                            'AadhaarNo'         => $AadhaarNo,
                                            'ProductCode'       => $ProductCode
                                        ]
                                    ];
                                    $additionData = array_merge($additionData,$pos_details);
                                }
                                $additionData['enquiryId'] = $proposalDetail->user_product_journey_id ?? null;
                                $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLEAR_INSPECTION_STATUS'), $inspection_data, 'icici_lombard', $additionData);
                                $inspectionFinalStatusResponse = $get_response['response'];
                          
                                $inspectionFinalStatusResponse = json_decode($inspectionFinalStatusResponse, true);
                                
                                if (isset($inspectionFinalStatusResponse['vehicleInspectionStatus']) && $inspectionFinalStatusResponse['vehicleInspectionStatus'] == 'PASS')
                                {
                                    DB::table('cv_breakin_status')
                                    ->where('breakin_number', $brknIdd)
                                    ->where('ic_id', 40)
                                    ->update([
                                        'breakin_status_final' => STAGE_NAMES['INSPECTION_APPROVED'],
                                        'updated_at' => date('Y-m-d H:i:s')
                                    ]);
                                    
                                    updateJourneyStage([
                                        'user_product_journey_id' => $proposalDetail->user_product_journey_id,
                                        'stage' => STAGE_NAMES['INSPECTION_ACCEPTED']
                                    ]);

                                    $journey_payload = DB::table('cv_journey_stages')->where('proposal_id', $proposalDetail->user_proposal_id)
                                    ->first();

                                    DB::table('cv_breakin_status')
                                    ->where('breakin_number', $brknIdd)
                                    ->where('ic_id', 40)
                                    ->update([
                                        'breakin_status_final' => STAGE_NAMES['INSPECTION_APPROVED'],
                                        'payment_url' => str_replace('quotes','proposal-page',$journey_payload->proposal_url),
                                        'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                                        'updated_at' => date('Y-m-d H:i:s')
                                    ]);

                                    UserProposal::where('user_product_journey_id', $proposalDetail->user_product_journey_id)
                                    ->update([
                                        'is_inspection_done' => 'Y'
                                    ]);

                                    $brknIdd . ' is recommended from IC';

                                    print_r([
                                        'status' => true,
                                        'message' => $brknIdd . 'is recommended from IC'
                                    ]);

                                }
                                else if (isset($inspectionFinalStatusResponse['vehicleInspectionStatus']) && $inspectionFinalStatusResponse['vehicleInspectionStatus'] == 'FAIL')
                                {
                                    $update_data = [
                                        'breakin_status' => STAGE_NAMES['INSPECTION_REJECTED'],
                                        'breakin_status_final' => STAGE_NAMES['INSPECTION_REJECTED'],
                                        'updated_at' => date('Y-m-d H:i:s')
                                    ];

                                    DB::table('cv_breakin_status')
                                        ->where('breakin_number', $brknIdd)
                                        ->where('ic_id', 40)
                                        ->update($update_data);

                                    updateJourneyStage([
                                        'user_product_journey_id' => $proposalDetail->user_product_journey_id,
                                        'stage' => STAGE_NAMES['INSPECTION_REJECTED']
                                    ]);

                                    $brknIdd . ' is rejected from IC';

                                    print_r([
                                            'status' => true,
                                            'message' => $brknIdd . 'is rejected from IC'
                                        ]);

                                    }

                            }
                            //elseif($brekin_recommeded_id->breakin_status == 'Reject' || $brekin_recommeded_id->breakin_status == 'Rejected' )
                            else if($brekin_recommeded_id->breakin_status == STAGE_NAMES['INSPECTION_REJECTED'])
                            {
                                $update_data = [
                                    'breakin_status' => STAGE_NAMES['INSPECTION_REJECTED'],
                                    'breakin_status_final' => STAGE_NAMES['INSPECTION_REJECTED'],
                                    'updated_at' => date('Y-m-d H:i:s')
                                ];

                                DB::table('cv_breakin_status')
                                    ->where('breakin_number', $brknIdd)
                                    ->where('ic_id', 40)
                                    ->update($update_data);

                                updateJourneyStage([
                                    'user_product_journey_id' => $proposalDetail->user_product_journey_id,
                                    'stage' => STAGE_NAMES['INSPECTION_REJECTED']
                                ]);

                                $brknIdd . ' is rejected from IC';

                                print_r([
                                        'status' => true,
                                        'message' => $brknIdd . 'is rejected from IC'
                                    ]);
                            }
                            else
                            {
                                $update_data = [
                                    'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                    'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                                    'updated_at' => date('Y-m-d H:i:s')
                                ];

                                DB::table('cv_breakin_status')
                                    ->where('breakin_number', $brknIdd)
                                    ->where('ic_id', 40)
                                    ->update($update_data);

                                updateJourneyStage([
                                    'user_product_journey_id' => $proposalDetail->user_product_journey_id,
                                    'stage' => STAGE_NAMES['INSPECTION_PENDING']
                                ]);

                                echo 'Inspection for ' .$brknIdd . ' is Pending from IC';
                                print_r([
                                        'status' => true,
                                        'message' => 'Inspection for ' .$brknIdd . 'is Pending from IC'
                                    ]);

                            }

                        }
                    }
                }
                else
                {
                    print_r([
                        'status' => false,
                        'message' => 'No Data Found',
                        'data'    => $inspectionStatusResponse
                    ]); 
                    
                }
            }
            else
            {
                print_r([
                    'status' => false,
                    'message' => 'No Data Found'
                ]);           
            }
        }
        else
        {
            echo 'Issue in token generation';
        }
    }
}
