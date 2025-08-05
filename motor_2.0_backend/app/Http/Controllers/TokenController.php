<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\UserProductJourney;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\QuoteLog;
use Illuminate\Support\Facades\DB;
use App\Models\JourneyStage;
use App\Models\UserTokenRequestResponse;
use Illuminate\Support\Facades\Http;

class TokenController extends Controller
{
    public function tokenService(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        
        $bool = config('constants.motorConstant.BROKER_USER_CREATION_API_no_proxy') == 'true' ? true : false;
        $url = config('constants.motorConstant.TOKEN_VALIDATE_URL');
       // $url = "https://uatdashboard.lifekaplan.com/validate_token";
        $token_data = [
            'token'             => $request->token,
            'skip_validation'   => 'Y'            
        ];
        $data = $bool
                ? Http::withoutVerifying()->post($url, $token_data)->json()
                : httpRequestNormal($url, 'POST', $token_data)['response'];
        $UserTokenRequestResponse = UserTokenRequestResponse::create([
            'user_type' => $data['data']['usertype'] ?? NULL,
            'request' => json_encode($request->all()),
            'response' => json_encode($data),
            'url'       => $url
        ]);
        
        if(empty($data['data']['seller_name']))
        {
            return response()->json([
                "status" => false,
                "msg" => 'Invalid Token...!',
            ]);
        }
        
//        $data = '{
//    "status": "true",
//    "data": {
//        "seller_id": "29",
//        "usertype": "U",
//        "seller_type": "U",
//        "seller_name": " ",
//        "user_name": "8197175063",
//        "seller_gender": "",
//        "email": "ankit.kumar@fyntune.com",
//        "mobile": "8197175063",
//        "first_name": "",
//        "last_name": "",
//        "unique_number": "8197175063",
//        "aadhar_no": "8197175063",
//        "pan_no": "8197175063",
//        "city": "",
//        "source": "website",
//        "agent_id": "29",
//        "redirection_link": "https://uatdashboard.lifekaplan.com/customer/buy_now",
//        "token_created_at": "2023-05-04 17:38:47",
//        "user_id": "",
//        "branch_code": "",
//        "branch_name": "",
//        "channel_id": "",
//        "channel_name": "",
//        "region_id": "",
//        "region_name": "",
//        "zone_id": "",
//        "zone_name": "",
//        "pos_key_account_manager": "",
//        "encrypted_form_data": [
//            {
//                "user_details": {
//                    "seller_type": "U",
//                    "seller_username": "8805685311",
//                    "lob": "Car",
//                    "first_name": "Amit",
//                    "last_name": "Patil",
//                    "email_id": "amit.p@fyntune.com",
//                    "gender": "MALE",
//                    "source": "TML",
//                    "return_url": ""
//                },
//                "additional_info": {
//                    "chassis_number": "MTLOU7680973565",
//                    "engine_number": "DGT897686K98633",
//                    "vehicle_type": "RENEW",
//                    "variant_id": "2",
//                    "model_id": "1",
//                    "manf_id": "1",
//                    "fuel_type": "PETROL",
//                    "registration_date": "2018-04-28",
//                    "existing_policy_expiry_date": "2023-04-28",
//                    "previous_ncb": "25",
//                    "claim_status": "N",
//                    "registration_number": "MH-46-BV-6002",
//                    "owner_type": "IND",
//                    "rto_number": "MH-46",
//                    "policy_source": "TML"
//                }
//            }
//        ]
//    }
//}';
        
        if(empty($data['data']['encrypted_form_data']))
        {
            return [
                'status'    => true,
                'msg' => 'Data not Found'
            ];
            
        }
       $payload =  json_decode($data['data']['encrypted_form_data'],true);
       $payload['token'] = $request->token;
       $payload['user_token_id'] = $UserTokenRequestResponse->id;
       
       $LeadController = new LeadController;
       return $getVehicleDetails = $LeadController->journeyRedirection(request()->replace($payload));
    }
}
