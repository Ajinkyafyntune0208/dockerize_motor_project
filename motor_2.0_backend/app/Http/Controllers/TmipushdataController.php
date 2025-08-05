<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Models\{
    UserProposal,
    DatapushReqResModel
};

use Illuminate\Support\Facades\Log;


class TmipushdataController extends Controller
{
    public static function pushapidata($enquiryId)
    {
        // Encryption key & IV for DEV & QA Environment
        $secret_key = config("TMI_PUSH_DATA_SECRET_KEY");
        $secret_iv = config("TMI_PUSH_DATA_SECRET_IV");

        // $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
        $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();

        $chassis = $proposal["chassis_number"];
        $vhregno = $proposal["vehicale_registration_number"];

        $current =now();
        $data = [
            "endpoint" => "/api/wrapper-service/update-lead-generation-status",
            "timestamp" =>$current ,
            "body" => [
                "chassis_number" => $chassis,
                "registration_number" => $vhregno,
                "lead_generation_status" => STAGE_NAMES['POLICY_ISSUED'],
            ],
        ];
        //converting into json string format for custom encryption
        $Jdata = json_encode($data, JSON_UNESCAPED_SLASHES);
        $ecpt = self::hookdataenc($Jdata, "E", $secret_key, $secret_iv); //encrypting data we need to send
        $decpt = self::hookdatadenc($ecpt, "D", $secret_key, $secret_iv); //decrypting the same data to save it in table datapushresreq
        // dd($ecpt);
        // dd($decpt);
        if ($ecpt == "failed" || $decpt == "failed") {
            return [
                'status' => false,
                "message" => "error while encrypting",
            ];
        }

        $response = httpRequest('pushdata', [
            $ecpt,
        ], [], [], [], [], false);

        $respstatus = $response["response"]["status"];
        $status = $respstatus == 0 ? "SUCCESS" : "FAILED";

        $datapushsaved = DatapushReqResModel::create([
            "enquiry_id" => $enquiryId ?? "",
            "url" => $response["url"],
            "request_headers" => $response["request_headers"],
            "dataenc" => $ecpt ?? "",
            "datadenc" => $decpt ?? "",
            "status" => $status ?? "",
            "status_code" => $response["status"],
            "request" => $data ?? "",
            "response" => $response ?? "not set yet",
        ]);

        if ($datapushsaved) {

            return $response;

        } else {
            //handling error if it fails to store in datapush_req_res
            return [
                'status' => false,
                "message" => "Please check some error occurred while saving",
            ];
        }
     
    }
    // public function static pushapidata($name,$data,$enquiryid,$enablenc=true, Request $request)
    // {
    //     // ==========================================================================
    //     // Note: While passing $data to this method it should be in array format
    //     //       => $name is the name mentioned in third_party_settings table
    //     //       => Before using this method , it is must to set it in third_party_settings
    //     //       => endpoint and timestamp (use carbon::now()) should be mentioned in $data
    //     // ==========================================================================

    //     //sample $data
    //     // $data = [
    //     //     "endpoint"=> "/api/wrapper-service/update-lead-generation-status",
    //     //     "timestamp"=> 1689077885374,
    //     //     "body"=> [
    //     //         "chassis_number"=> "ERDFCVBGTYHN65432",
    //     //         "registration_number"=> "SCVEP05707",
    //     //         "lead_generation_status"=> STAGE_NAMES['POLICY_ISSUED']
    //     //     ]
    //     // ];

    //     $secret_key = 'mPSx8o6tSvu1yEzBEobg1ah96HBAn2EC';
    //     $secret_iv =  'tF4W4pC5UsEvJWFm';

    //     $headers = $request->header();

    //     //converting array into json string format for custom encryption
    //     $Jdata = json_encode($data,JSON_UNESCAPED_SLASHES);

    //     $ecpt = self::hookdataenc($Jdata,"E",$secret_key,$secret_iv); //encrypting data we need to send
    //     $decpt = self::hookdatadenc($ecpt,"D",$secret_key,$secret_iv); //decrypting the same data to save it in table datapushresreq

    //     if( $ecpt == "failed" || $decpt == "decpt" ){
    //         //handling error if there is issue while encryption
    //         return [
    //             'status'=>false,
    //             "message"=>"error while encrypting"
    //         ];
    //     }

    //     if ($enablenc){

    //         $response = httpRequest($name, [
    //             $ecpt
    //         ], [], [], [], [], false);

    //     } else {

    //         $response = httpRequest($name, [
    //             $Jdata
    //         ], [], [], [], [], false);
    //     }

    //     $respstatus = $response["response"]["status"];
    //     $status = $respstatus==0 ? "success" : "failed";

    //     $datapushsaved = DatapushReqRes::create([
    //         "enquiry_id" => $enquiryid ?? "Not Found",
    //         "method_name" => $name ?? "Not Found",
    //         "url" => $data["endpoint"] ?? $decpt["endpoint"] ?? "Not Found",
    //         "headers" => $headers ?? "Not Found",
    //         "dataenc" => $ecpt ?? "Not Found",
    //         "datadenc" => $decpt ?? "Not Found",
    //         "status" => $status ?? "Not Found",
    //         "request"=> $data ?? "Not Found",
    //         "response" => $response ?? "Not Found",
    //     ]);
    //     if ($datapushsaved){

    //         return $response;

    //     } else {
    //         //handling error if it fails to store in datapush_req_res
    //         return [
    //             'status'=>false,
    //             "message"=>"Please check some error occurred while saving"
    //         ];
    //     }

    // }
    // public function testingtmi(){

    //     $testing= updateJourneyStage([
    //         'user_product_journey_id' => 5,
    //         "stage"=>STAGE_NAMES['QUOTE']
    //     ]);
    //     $testing= updateJourneyStage([
    //         'user_product_journey_id' => 5,
    //         "stage"=>STAGE_NAMES['POLICY_ISSUED']
    //     ]);
    //     if($testing){
    //         return [
    //           "status" => "Success"  
    //         ];
    //     }else {
    //         return [
    //             "status" => "failed"  
    //           ];
    //     }
    // }
    public static function hookdataenc($data, $type, $secret_key, $secret_iv)
    {

        $encrypt_method = "AES-256-CBC";
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16);
        if ($type == 'E') {
            $output = openssl_encrypt($data, $encrypt_method, $key, 0, $iv);
            $output = base64_encode($output);
        } else {
            // $output = "en-crypt failed";
            $output = "failed";

        }
        return $output;
    }
    public static function hookdatadenc($data, $type, $secret_key, $secret_iv)
    {

        $encrypt_method = "AES-256-CBC";
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16);

        if ($type == 'D') {

            $output = openssl_decrypt(base64_decode($data), $encrypt_method, $key, 0, $iv);
        } else {
            // $output = "De-crypt failed";
            $output = "failed";

        }
        return $output;
    }
}
