<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use App\Models\JourneyStage;
use Illuminate\Http\Request;
use App\Models\UserProductJourney;
use App\Models\AbiblQuoteUrlRequestResponse;

class AbiblController extends Controller
{
    public function quoteUrl(Request $request)
    {
        $quote_url_log = AbiblQuoteUrlRequestResponse::create([
            'registration_no' => $request->rcNumber ?? null,
            "request" => json_encode($request->all())
        ]);

        $validateData = $request->validate([
            'rcNumber' => 'required',
            'source' => 'required',
            'firstName' => 'nullable',
            'lastName' => 'nullable',
            'emailId' => 'nullable',
            'mobileNo' => 'nullable',
            'campaign_id' => 'nullable',
            'sub_source' => 'nullable',
            "payloadId" => "nullable"
        ]);
        $restricted_vehicle_no = \App\Models\RestrictedVehicleNo::where([
            'vehicle_registration_number' => $validateData['rcNumber'] ?? null,
        ])->first();
        if ($restricted_vehicle_no) {
            return response()->json([
                'status' => false,
                'message' => 'Vehicle Registration Number Has been Block...!'
            ]);
        }
        $user_product_journey_id = UserProductJourney::create([
            'product_sub_type_id' => $validateData['productSubTypeId'] ?? null,
            'user_fname' => $validateData['firstName'] ?? null,
            'user_lname' => $validateData['lastName'] ?? null,
            'user_email' => $validateData['emailId'] ?? null,
            'user_mobile' => (strlen($validateData['mobileNo']) == 12 ? Str::substr($validateData['mobileNo'], 2) : $validateData['mobileNo'] ) ?? null,
            'sub_source' => $validateData['sub_source'] ?? null,
            'campaign_id' => $validateData['campaign_id'] ?? null,
            'lead_id' => $validateData['payloadId'] ?? null,
        ]);

        $request->replace([
            "stage" => "1",
            "userProductJourneyId" => $user_product_journey_id->journey_id,
            "enquiryId" => $user_product_journey_id->journey_id,
            "whatsappConsent" => true,
        ]);
        $common_controller = new CommonController();
        $common_controller->saveQuoteRequestData($request);

        $section = 'car';
        $productSubType = "1";
        $request->replace([
            'enquiryId' => $user_product_journey_id->journey_id,
            'registration_no' => $validateData['rcNumber'] ?? null,
            "productSubType" => $productSubType,
            "section" => $section,
            "is_renewal" => "Y",
        ]);
        $vehicleDetails = json_decode($common_controller->getVehicleDetails($request)->content(), true);

        if (isset($vehicleDetails['data']['ft_product_code']) && $vehicleDetails['data']['ft_product_code'] != $section) {
            $request->replace([
                'enquiryId' => $user_product_journey_id->journey_id,
                'registration_no' => $validateData['rcNumber'] ?? null,
                "productSubType" => $productSubType,
                "section" => $section,
                "is_renewal" => "Y",
            ]);
            $vehicleDetails = json_decode($common_controller->getVehicleDetails($request)->content(), true);
        }

        if ((isset($vehicleDetails['data']['ft_product_code']) || isset($vehicleDetails['data']['additional_details']['productSubTypeId'])) && (isset($vehicleDetails['data']['status']) && $vehicleDetails['data']['status'] == 100) && isset($vehicleDetails['data']['additional_details'])) {
            if (isset($vehicleDetails['data']['ft_product_code'])) {
                $productSubType = \App\Models\MasterProductSubType::with('parent')->where('product_sub_type_code', $vehicleDetails['data']['ft_product_code'])->first()->parent->product_sub_type_id;
            }/*  else if(isset($vehicleDetails['data']['additional_details']['productSubTypeId'])){
                $vehicleDetails['data']['additional_details']['productSubTypeId'];
            } */

            if (isset($vehicleDetails['data']['ft_product_code'])) {
                if ($vehicleDetails['data']['ft_product_code'] == 'car') {
                    $url = config('constants.motorConstant.CAR_FRONTEND_URL') . '/quotes?enquiry_id=' . $user_product_journey_id->journey_id;
                } else if ($vehicleDetails['data']['ft_product_code'] == 'bike') {
                    $url = config('constants.motorConstant.BIKE_FRONTEND_URL') . '/quotes?enquiry_id=' . $user_product_journey_id->journey_id;
                } else {
                    $url = config('constants.motorConstant.CV_FRONTEND_URL') . '/quotes?enquiry_id=' . $user_product_journey_id->journey_id;
                }
            } else if (isset($vehicleDetails['data']['additional_details']['productSubTypeId'])) {
                $productSubType = $vehicleDetails['data']['additional_details']['productSubTypeId'];
                if (get_parent_code($vehicleDetails['data']['additional_details']['productSubTypeId']) == 'CAR') {
                    $url = config('constants.motorConstant.CAR_FRONTEND_URL') . '/quotes?enquiry_id=' . $user_product_journey_id->journey_id;
                } else if (get_parent_code($vehicleDetails['data']['additional_details']['productSubTypeId']) == 'BIKE') {
                    $url = config('constants.motorConstant.BIKE_FRONTEND_URL') . '/quotes?enquiry_id=' . $user_product_journey_id->journey_id;
                } else {
                    $url = config('constants.motorConstant.CV_FRONTEND_URL') . '/quotes?enquiry_id=' . $user_product_journey_id->journey_id;
                }
            }
            updateJourneyStage([
                'user_product_journey_id' => $user_product_journey_id->user_product_journey_id,
                'quote_url' => $url,
                'stage' => STAGE_NAMES['QUOTE']
            ]);

            $request->replace([
                "isRenewalRedirection" => "N",
                "enquiryId" => $user_product_journey_id->journey_id,
                "vehicleRegistrationNo" => $validateData['rcNumber'] ?? null,
                "userProductJourneyId" => $user_product_journey_id->journey_id,
                "corpId" => "",
                "userId" => null,
                "productSubTypeId" =>  $productSubType,
                "fullName" => ($validateData['firstName'] ?? null) . " " . ($validateData['lastName'] ?? null),
                "firstName" => $validateData['firstName'] ?? null,
                "lastName" => $validateData['lastName'] ?? null,
                "emailId" => $validateData['emailId'] ?? null,
                "mobileNo" => (strlen($validateData['mobileNo']) == 12 ? Str::substr($validateData['mobileNo'], 2) : $validateData['mobileNo'] ) ?? null,
                "policyType" => $vehicleDetails['data']['additional_details']['policyType'],
                "businessType" => $vehicleDetails['data']['additional_details']['businessType'],
                "rto" => $vehicleDetails['data']['additional_details']['rto'],
                "manufactureYear" => $vehicleDetails['data']['additional_details']['manufactureYear'],
                "version" => $vehicleDetails['data']['additional_details']['version'],
                "versionName" => $vehicleDetails['data']['additional_details']['versionName'],
                "vehicleRegisterAt" => $vehicleDetails['data']['additional_details']['vehicleRegisterAt'],
                "vehicleRegisterDate" => $vehicleDetails['data']['additional_details']['vehicleRegisterDate'],
                "vehicleOwnerType" => $vehicleDetails['data']['additional_details']['vehicleOwnerType'],
                "hasExpired" => $vehicleDetails['data']['additional_details']['hasExpired'],
                "isNcb" => $vehicleDetails['data']['additional_details']['isNcb'],
                "isClaim" => $vehicleDetails['data']['additional_details']['isClaim'],
                "fuelType" => $vehicleDetails['data']['additional_details']['fuelType'],
                "vehicleUsage" => $vehicleDetails['data']['additional_details']["vehicleUsage"],
                "vehicleLpgCngKitValue" => "",
                "previousInsurer" => "",
                "previousInsurerCode" => "",
                "previousPolicyType" => $vehicleDetails['data']['additional_details']["previousPolicyType"],
                "modelName" => $vehicleDetails['data']['additional_details']['modelName'],
                "manfactureName" => $vehicleDetails['data']['additional_details']['manfactureName'],
                "ownershipChanged" => $vehicleDetails['data']['additional_details']['ownershipChanged'],
                "leadJourneyEnd" => true,
                "stage" => 11,
                "applicableNcb" => $vehicleDetails['data']['additional_details']['applicableNcb'],
                "manfactureId" => $vehicleDetails['data']['additional_details']['manfactureId'],
                "model" => $vehicleDetails['data']['additional_details']['model'],
                "policyExpiryDate" => $vehicleDetails['data']['additional_details']['policyExpiryDate'],
                "previousNcb" => $vehicleDetails['data']['additional_details']['previousNcb'],
                'journeyType' => base64_encode($validateData['source']) ?? null
            ]);
            $common_controller->saveQuoteRequestData($request);
            if (isset($vehicleDetails['data']['results'][0]['vehicle']['eng_no']) && isset($vehicleDetails['data']['results'][0]['vehicle']['chasi_no'])) {
                $request->merge([
                    "engineNo" => $vehicleDetails['data']['results'][0]['vehicle']['eng_no'],
                    "chassisNo" => $vehicleDetails['data']['results'][0]['vehicle']['chasi_no'],
                    "vehicleColor" => removeSpecialCharactersFromString($vehicleDetails['data']['results'][0]['vehicle']['color'] ?? "", true)
                ]);
            }

            if (isset($vehicleDetails['data']['additional_details']["previous_insurer"])) {
                $request->merge(["previous_insurer" => $vehicleDetails['data']['additional_details']["previous_insurer"]]);
            }
            if (isset($vehicleDetails['data']['additional_details']["previous_insurer_code"])) {
                $request->merge(["previous_insurer_code" => $vehicleDetails['data']['additional_details']["previous_insurer_code"]]);
            }
            $common_controller->saveQuoteRequestData($request);
            $request->replace(["enquiryId" => $user_product_journey_id->journey_id, "leadStageId" => 2]);
            $common_controller->updateUserJourney($request);
        } else {
            $response = [
                'status' => false,
                'message' => 'Data Not Found',
                'data' => [
                    'url' => shortUrl(url('api/abibl/wrapper?product_sub_type_id=' . $productSubType))['response']['short_url'],
                    'vehicle_details' => $vehicleDetails['data']['results'][0] ?? $vehicleDetails['data'],
                    'fastlane_records' => $vehicleDetails['data']['status'] == 100 ? 1 : 3,
                ]
            ];
            if ($validateData['sub_source'] == 'WhatsApp') {
                httpRequest("whats_app_two_way", [
                    "send_to" => $validateData["mobileNo"],
                    "msg_type" => "TEXT",
                    "method" => "SENDMESSAGE",
                    "msg" => "Hi {$validateData["firstName"]},\n\nPlease click the below link to get quotes for your vehicle.\n\n{$response["data"]["url"]}"
                ]);
            }
            $quote_url_log->update(['response' => json_encode($response)]);
            return response()->json($response);
        }
        $response = [
            'status' => true,
            'message' => 'Data Found',
            'data' => [
                'url' => shortUrl(url('api/abibl/wrapper?enquiry_id=' . $user_product_journey_id->journey_id))['response']['short_url'],
                'vehicle_details' => $vehicleDetails['data']['results'][0] ?? $vehicleDetails['data'],
                'fastlane_records' => $vehicleDetails['data']['status'] == 100 ? 1 : 3,
            ]
        ];
        
        if($validateData['sub_source'] == 'WhatsApp'){
            httpRequest("whats_app_two_way", [
                "send_to" => $validateData["mobileNo"],
                "msg_type" => "TEXT",
                "method" => "SENDMESSAGE",
                "msg" => "Hi {$validateData["firstName"]},\n\nPlease click the below link to check quotes for your {$response['data']['vehicle_details']['vehicle']['fla_maker_desc']}-{$response['data']['vehicle_details']['vehicle']['fla_model_desc']}.\n\n{$response["data"]["url"]}"
            ]);
        }
            $quote_url_log->update(['response' => json_encode($response)]);
        return response()->json($response);
    }

    public function wrapper(Request $request)
    {
        if (!isset($request->enquiry_id) || $request->enquiry_id == null) {
            if (isset($request->product_sub_type_id)) {

                if (get_parent_code($request->product_sub_type_id) == 'CAR') {
                    $url = config('constants.motorConstant.CAR_FRONTEND_URL') . '/lead-page';
                } else if (get_parent_code($request->product_sub_type_id) == 'BIKE') {
                    $url = config('constants.motorConstant.BIKE_FRONTEND_URL') . '/lead-page';
                } else {
                    $url = config('constants.motorConstant.CV_FRONTEND_URL') . '/lead-page';
                }
            }
            return redirect($url);
        }
        $user_product_journey_id = customDecrypt($request->enquiry_id);
        $enquiry_id = $request->enquiry_id;
        $journey_stage = JourneyStage::where('user_product_journey_id', $user_product_journey_id)->first();

        if ($journey_stage->stage == STAGE_NAMES['QUOTE']) {
            return redirect($journey_stage->quote_url);
        } elseif ($journey_stage->stage == STAGE_NAMES['POLICY_ISSUED']) {
            return response('Journey has been completed...!');
        } else {
            return redirect($journey_stage->proposal_url);
        }
    }

    public function blockList(Request $request)
    {
        $request->validate([
            'rcNumber' => 'required',
            'status' => 'required'
        ]);
        \App\Models\RestrictedVehicleNo::firstOrCreate([
            'vehicle_registration_number' => $request->rcNumber,
            'status' => $request->status,
        ], [
            'vehicle_registration_number' => $request->rcNumber,
            'status' => $request->status,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Vehicle Registration No Added Successfully...!',
        ], 200);
    }
}
