<?php

namespace App\Http\Controllers;
use Ixudra\Curl\Facades\Curl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
include_once app_path() . '/Helpers/CarWebServiceHelper.php';
class IciciMmvController extends Controller
{
    function getIciciShowRoomPrice(Request $request)
    {
        if($request->section == 'cv')
        {
            $data = Storage::get('mmv_masters/production/icici_lombard_pcv_model_master.json');
            $deal_id = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID');
        }else if($request->section == 'gcv')
        {
            $data = Storage::get('mmv_masters/production/icici_lombard_gcv_model_master.json');
            $deal_id = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV_TP');
        }else{
            return [
                'status'=>false,
                'message' => 'Please Specify The Section cv/gcv'
            ];
        }
        $data = json_decode($data, true);
        $resultResponse = [];
        // token Generation

        $additionData = [
            'requestMethod' => 'post',
            'type' => 'tokenGeneration',
            'section' => $request->section,
            'productName'  => 'rollover',
            'enquiryId' => '12234324',
            'transaction_type' => 'quote'
        ];

        $tokenParam = [
            'grant_type' => 'password',
            'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME'),
            'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD'),
            'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID'),
            'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET'),
            'scope' => 'esbmotormodel',
        ];


        $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR'), http_build_query($tokenParam), 'icici_lombard', $additionData);
        $token = $get_response['response'];
        $token = json_decode($token, true);
        try
        {
            foreach ($data as $key => $value) 
            {

                if($value['exshowroom_price'] == '' || $value['exshowroom_price'] == null)
                    {
                        $request = [
                            "manufacturercode" => $value['manf_code'],
                            "BusinessType" => $request->businesstype,
                            "rtolocationcode" => $request->rtolocationcode,
                            "DeliveryOrRegistrationDate" => $request->RegistrationDate,
                            "PolicyStartDate" => $request->PolicyStartDate,
                            "DealID" => $deal_id,
                            "vehiclemodelcode" => $value['model_code'],
                            "correlationId" => getUUID()
                        ];
                
                
                        $cRequest = json_encode($request);
                
                        $curl = Curl::to(config('constants.IcConstants.icici_lombard.CV_IDV_END_POINT_URL'))
                                ->withHeader('Content-type: application/json')
                                ->withHeader('Authorization: Bearer '.$token)
                                ->withHeader('Accept: application/json')
                                ->withData($cRequest);
                
                        $curlResponse = $curl->post();
                
                        $Response = [];
                
                        $result_data = json_decode($curlResponse, true);
                
                
                        $Response['manf_name']  =  $value['manf_name'];
                        $Response['model_name'] =  $value['model_name'];
                        $Response['vehiclesellingprice']   =  $result_data['vehiclesellingprice'];
                
                        if ($result_data['vehiclesellingprice'] !== null) 
                        {
                            array_push($resultResponse, $Response);
                
                            DB::table('mmv_missing_exshowroom')
                                ->insert([
                                    'manf_name' => $value['manf_name'],
                                    'model_name' => $value['model_name'],
                                    'model_code' => $value['model_code'],
                                    'showroom_price' => $result_data['vehiclesellingprice']
                                ]);
                
                        }
                    }else
                    {
                        return [
                            'status' => false,
                            'message' => 'No Record Found'
                        ];
                    }
            
            }
        }catch(\exception $e)
        { 
            return [
                'status' => false,
                'message' => $e->getMessage(),
                'line_no' => $e->getLine(),
                'file' => pathinfo($e->getFile())['basename']
            ];
        }
        return $resultResponse;
    }

    
}
