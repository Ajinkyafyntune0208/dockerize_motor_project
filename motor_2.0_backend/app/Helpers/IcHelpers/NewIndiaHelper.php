<?php
function RcVerification($request)
{
    if($request->section == 'CAR')
    {
        include_once app_path().'/Helpers/CarWebServiceHelper.php';
    }
    else if($request->section == 'BIKE')
    {
        include_once app_path().'/Helpers/BikeWebServiceHelper.php';
    }
    else
    {
        include_once app_path().'/Helpers/CvWebServiceHelper.php';
    }
    $request_data = [
        'PartnerID'         => config('NEW_INDIA_PARTNER_ID'),#AG_UAT18 #PAYTMUSER
        'VehicleNumber'     => trim(str_replace('-','',$request->reg_no))
    ];

    $additional_data = [
        'enquiryId'         => $request->enquiry_id,
        'requestMethod'     => 'post',
        'productName'       => 'Rc Verification',
        'company'           => 'new_india',
        'section'           => $request->section,
        'method'            => 'Rc Verification',
        'transaction_type'  => 'proposal',
        //'authorization' => [config('constants.IcConstants.new_india.AUTH_NAME_NEW_INDIA'), config('constants.IcConstants.new_india.AUTH_PASS_NEW_INDIA')],
        'authorization' => [
            config('NEW_INDIA_RC_USER_ID'), //Userid: UATRCV
            config('NEW_INDIA_RC_PASSWORD') //Password: Nia@1234
        ]
    ];
    //https://uatb2bug.newindia.co.in/b2b/rcVerification
    $response = getWsData(config('NEW_INDIA_RC_VERIFICATION_URL'), $request_data, 'new_india', $additional_data);
    $response = json_decode($response['response'],true);
    if(!isset($response['Status']))
    {
        return [
            'status'    => false,
            'msg'      => 'Rc Verification service is not enabled'
        ];
    }
    $status = strtoupper($response['Status']) == 'SUCCESS' ? true : false;
    $msg = $response['Remarks'];

    return [
        'status'    => $status,
        'msg'      => 'Error from Rc Verification : '.$msg
    ];

}