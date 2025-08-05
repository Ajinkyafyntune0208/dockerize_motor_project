<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IciciMasterDownloadController extends Controller
{
    //
    public function index()
    {
        if (!auth()->user()->can('icici_master.list')) {
            abort(403, 'Unauthorized action.');
        }
        return view('icici_master.index');
    }
    public function geticmaster(Request $request)
    {
        switch($request->section)
        {
            case 'car':
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR');
            break;
            case 'bike':
                $deal_id = config('constants.IcConstants.icici_lombard.PROPOSAL_DEAL_ID_ICICI_LOMBARD_BIKE');
            break;
            case 'cv':
                $deal_id = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID');
            break;
            case 'gcv':
                $deal_id = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_DEAL_ID_GCV_TP');
            break;
        }
        if($request->master_type == 'GetVehicleRTOMasterDetails')
        {
            $url = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_RTO_URL');
            $filename = strtoupper($request->section).' PACKAGE POLICY_RTO.csv';
        }else{
            $url = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_MMV_URL');
            switch($request->section)
            {
                case 'car':
                    $filename = 'FOUR WHEELER PACKAGE POLICY_Make_Model_Master.csv';
                break;
                case 'bike':
                    $filename = 'TWO WHEELER PACKAGE POLICY_Make_Model_Master.csv';
                break;
                case 'cv':
                    $filename = 'PASSENGER CARRYING PACKAGE POLICY_Make_Model_Master.csv';
                break;
                case 'gcv':
                    $filename = 'GOODS CARRYING PACKAGE POLICY_Make_Model_Master.csv';
                break;
            }
        }
        $enquiryId = rand();
        $additionData = [
            'requestMethod' => 'post',
            'type' => 'tokenGeneration',
            'section' => 'taxi',
            'enquiryId' => $enquiryId,
            'transaction_type' => 'quote',
            'productName'  => $request->section,
        ];
    
        $tokenParam = [
            'grant_type' => 'password',
            'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME'),
            'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD'),
            'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID'),
            'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET'),
            'scope' => 'esbmotormaster',
        ];
    
        include_once app_path() . '/Helpers/CvWebServiceHelper.php';
        $get_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL'), http_build_query($tokenParam), 'icici_lombard', $additionData);
        $token = $get_response['response'];
        $token = json_decode($token, true);
        $GetVehicleRtoMaster = [
            "DealID" => $deal_id,
            "CorrelationId" => getUUID()
        ];
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>json_encode($GetVehicleRtoMaster),
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer '.$token['access_token'],
            'Content-Type: application/json',
            'Cookie: f5avraaaaaaaaaaaaaaaa_session_=CKBLGKHEGGHHKGMMKOOLPGLJNJMMIKCHNDKCOGMGGHICGLJGFIJNHEBFPANLNNOIPPFDDLFJOAIJKFANEBEANLEKMFFLCMHGACCOJOEPGMBMILBECPOJDPPCAMONKMFK'
        ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $check=json_decode($response);
        if(isset($check->message))
        {
            return redirect()->route('admin.icici-master.index')->with([
                'status' => $check->message,
                'class' => 'danger',
            ]);
        }else{
            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename="'.$filename.'"');
            echo $response;
            return redirect()->route('admin.icici-master.index')->with([
                'status' => 'Your File Is Download Successfully!',
                'class' => 'success',
            ]);;
        }
        
        
        
    }
}
