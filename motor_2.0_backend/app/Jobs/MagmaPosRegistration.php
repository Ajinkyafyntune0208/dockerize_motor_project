<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Agents;
use Carbon\Carbon;
use App\Models\MagmaPosMapping;
use Illuminate\Http\Request;
use App\Models\MagmaPosReqResponse;
use DateTime;

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 1800); 

require_once app_path() . '/Helpers/CarWebServiceHelper.php';

class MagmaPosRegistration implements ShouldQueue
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
    public function handle(Request $request)
    {
        $return_data = [];

        $all_pos_data = Agents::whereNotIn('agent_id', MagmaPosMapping::pluck('agent_id'))
		->select('agents.*')
        ->where('usertype', 'P')
        ->where('status', 'Active')
        ->whereNotNull('pan_no')
        ->whereNotNull('aadhar_no')
        ->where('pan_no', '<>', '')
        ->orderBy('ag_id', 'DESC')
        ->limit(20)
        ->get();

        $startTime = new DateTime(date('Y-m-d H:i:s'));
        if(!empty($all_pos_data))
        {
            foreach ($all_pos_data as $key => $pos) 
            {   

                if($pos)
                {
                    $additionData = [
                        'requestMethod'     => 'post',
                        'method'            => 'tokenGeneration',
                        'type'              => 'tokenGeneration',
                        'productName'       => 'Magma POS Registration',
                        'section'           => 'Magma POS Registration',
                        'enquiryId'         => $pos->agent_id,
                        'transaction_type'  => 'Magma POS Registration'
                    ];
    
                    $tokenParam = [
                        'grant_type' => config('constants.IcConstants.magma.MAGMA_GRANT_TYPE'),
                        'username' => config('constants.IcConstants.magma.MAGMA_USERNAME'),
                        'password' => config('constants.IcConstants.magma.MAGMA_PASSWORD'),
                        'CompanyName' => config('constants.IcConstants.magma.MAGMA_COMPANYNAME'),
                    ];

                    $token = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN'), http_build_query($tokenParam), 'magma', $additionData);                

                    $token_data = json_decode($token['response'], true);

                    if(isset($token_data['access_token']))
                    {                  
                        $urlreq = config('constants.IcConstants.magma.END_POINT_URL_MAGMA_POS_CREATEURL');
                        $pos_details_submit = [
                            "IRDABrokerCode"        => config('constants.IcConstants.magma.MAGMA_INTERMEDIARYCODE'),
                            "EntityRelationShipCode"=> config('constants.IcConstants.magma.MAGMA_ENTITYRELATIONSHIPCODE'),
                            "IntermediaryCode"      => config('constants.IcConstants.magma.MAGMA_INTERMEDIARYCODE'),
                            "POSPName"              => $pos->agent_name,
                            "StateCode"             => "",
                            "StateName"             => "",
                            "CityName"              => "",
                            "PINCode"               => $pos->pincode,
                            "EmailID"               => $pos->email,
                            "MobileNo"              => $pos->mobile,
                            "LandLineNo"            => $pos->mobile,
                            "AdhaarCard"            => $pos->aadhar_no,
                            "PanCard"               => $pos->pan_no,
                            "BrokerPOSPCode"        => config('constants.IcConstants.magma.MAGMA_BROKERPOSPCode')//"005085"
                        ];
                       
                        $additionalData = [
                            'requestMethod'     => 'post',
                            'type'              => 'POS Submit',
                            'method'            => 'POS Submit',
                            'section'           => 'Magma POS Registration',
                            'productName'       => 'Magma POS Registration',
                            'token'             => $token_data['access_token'],
                            'enquiryId'         => $pos->agent_id,
                            'transaction_type'  => 'Magma POS Registration',
                            'headers'           => [
                                'Content-Type'  => 'application/json',
                                'Authorization' => 'Bearer' . ' ' .$token_data['access_token']
                            ]
                        ];
                        $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_POS_CREATEURL'), $pos_details_submit, 'magma', $additionalData);

                        $data_response = $get_response['response'];

                        $data_response = json_decode($data_response, true);

                        $endTime = new DateTime(date('Y-m-d H:i:s'));
                        
                        if(isset($data_response))
                        {
                            MagmaPosReqResponse::create([
                                'company'  => 'magma',
                                'section'  => json_encode($additionalData['section']),
                                'method_name' => json_encode($additionalData['method']),
                                'product'  => json_encode($additionalData['method']),
                                'method'   => 'post',
                                'request'  => json_encode($pos_details_submit),
                                'response' => json_encode($data_response),
                                'endpoint_url' => $urlreq,
                                'created_at' => date("Y-m-d H:i:s"),
                                'headers' => json_encode($additionalData['headers']),
                                'status'  => $data_response['ServiceResult'],
                                'message' => $data_response['ErrorText'],
                                'start_time' => $startTime,
                                'end_time' => $endTime,
                                'response_time' => $endTime->getTimestamp() - $startTime->getTimestamp(),
                                'agent_id' => $pos->agent_id
                            ]);

                            if($data_response['ServiceResult'] == 'Success')
                            {
                                MagmaPosMapping::updateorCreate(
                                    [ 'agent_id' => $pos->agent_id ],
                                    [
                                        'mhdipospcode'  => $data_response['OutputResult']['MHDIPOSPCode'] ?? NULL,
                                        'request'       => json_encode($pos_details_submit),
                                        'response'      => json_encode($data_response),
                                        'status'        => $data_response['ServiceResult'],
                                        'updated_at'    => date("Y-m-d H:i:s")
                                    ]
                                );
                                MagmaPosReqResponse::create([
                                    'company'  => 'magma',
                                    'section'  => json_encode($additionalData['section']),
                                    'method_name' => json_encode($additionalData['method']),
                                    'product'  => json_encode($additionalData['method']),
                                    'method'   => 'post',
                                    'request'  => json_encode($pos_details_submit),
                                    'response' => json_encode($data_response),
                                    'endpoint_url' => $urlreq,
                                    'created_at' => date("Y-m-d H:i:s"),
                                    'headers' => json_encode($additionalData['headers']),
                                    'status'  => $data_response['ServiceResult'],
                                    'message' => $data_response['ErrorText'],
                                    'start_time' => $startTime,
                                    'end_time' => $endTime,
                                    'response_time' => $endTime->getTimestamp() - $startTime->getTimestamp(),
                                    'agent_id' => $pos->agent_id
                                ]);    
                                $return_data[] = [
                                    'status' => true,
                                    'message' => "Agent registered successfully . ".$pos->agent_id
                                ];
                            }
                            else
                            {
                                MagmaPosReqResponse::create([
                                    'company'  => 'magma',
                                    'section'  => json_encode($additionalData['section']),
                                    'method_name' => json_encode($additionalData['method']),
                                    'product'  => json_encode($additionalData['method']),
                                    'method'   => 'post',
                                    'request'  => json_encode($pos_details_submit),
                                    'response' => json_encode($data_response),
                                    'endpoint_url' => $urlreq,
                                    'created_at' => date("Y-m-d H:i:s"),
                                    'headers' => json_encode($additionalData['headers']),
                                    'status'  => $data_response['ServiceResult'],
                                    'message' => $data_response['ErrorText'],
                                    'start_time' => $startTime,
                                    'end_time' => $endTime,
                                    'response_time' => $endTime->getTimestamp() - $startTime->getTimestamp(),
                                    'agent_id' => $pos->agent_id
                                ]);

                                MagmaPosMapping::updateorCreate(
                                    [ 'agent_id' => $pos->agent_id ],
                                    [
                                        'mhdipospcode'  => $data_response['OutputResult']['MHDIPOSPCode'] ?? NULL,
                                        'request'       => json_encode($pos_details_submit),
                                        'response'      => json_encode($data_response),
                                        'status'        => $data_response['ServiceResult'],
                                        'updated_at'    => date("Y-m-d H:i:s")
                                    ]
                                );
                               
                                $return_data[] =  [
                                    'status' => true,
                                    'message' => "Agent was already registered . ".$pos->agent_id
                                ];
                            }
                        }else
                        {
                            $return_data[] =  [
                                'status' => false,
                                'message' => "Error in Submit pos certificate service"
                            ];
                        }                        
                    }
                    else
                    {
                        $return_data[] =  [
                            'status' => false,
                            'message' => "Issue in Token Generation Service ".$pos->agent_id
                        ];
                    }               
                }else
                {
                    $return_data[] = 
                        [
                            'status' => false,
                            'message' => "Data not complete ".$pos->agent_id
                        ];
                }
            } 
        }else
        {
            $return_data[] =  
            [
                'status' => false,
                'message' => "No Agents to process"
            ];
            
        }   
        return $return_data;   
    }
}
