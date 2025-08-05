<?php

namespace App\Http\Controllers;

use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\CvAgentMapping;
use App\Models\JourneyStage;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GenerateLeadController extends Controller
{
    public function generateLead(Request $request, $leadSource)
    {
        $validator = Validator::make($request->all(), [
            'vehicleRegistrationNumber' => 'required'
        ]);

        if($validator->fails()){
            return response()->json(['status'=>false,'msg' => $validator->errors()]);
        }

        $rcNumber = getRegisterNumberWithHyphen(strtoupper($request->vehicleRegistrationNumber));
        $rtoCode = RtoCodeWithOrWithoutZero($rcNumber, true);

        //check whether one pay journey is already made for the RC number
        $userProductJourney = CorporateVehiclesQuotesRequest::whereIn('vehicle_registration_no', [
            $request->vehicle_registration_number, $rcNumber
        ])->join(
            'user_product_journey as up',
            'up.user_product_journey_id',
            '=',
            'corporate_vehicles_quotes_request.user_product_journey_id'
        )
        ->join(
            'cv_journey_stages as cv',
            'cv.user_product_journey_id',
            '=',
            'corporate_vehicles_quotes_request.user_product_journey_id'
        )
        ->where([
            'up.lead_source' => $leadSource
        ])
        ->select('cv.stage', 'cv.quote_url', 'cv.proposal_url')
        ->orderBy('cv.id', 'DESC')
        ->first();

        if (!empty($userProductJourney)) {
            $redirectUrl = $userProductJourney->proposal_url;
            if (in_array($userProductJourney->stage, [ STAGE_NAMES['QUOTE'], STAGE_NAMES['LEAD_GENERATION']])) {
                $redirectUrl = $userProductJourney->quote_url;
            }
            return redirect($redirectUrl);
        }

        //Only GCV journey will be done using one pay
        $productSubTypeId = 4;
        $userProductJourney = UserProductJourney::create([
            'product_sub_type_id' => $productSubTypeId,
            'lead_source' => $leadSource
        ]);

        // CvAgentMapping::create([
        //     'user_product_journey_id' => $userProductJourney->user_product_journey_id
        // ]);

        CvAgentMapping::updateOrCreate(
            ['user_product_journey_id' => $userProductJourney->user_product_journey_id],
            ['user_product_journey_id' => $userProductJourney->user_product_journey_id]
        );

        $commonCotroller = new CommonController;

        $request->replace([
            "stage" => 1,
            "userProductJourneyId" => $userProductJourney->journey_id,
            "enquiryId" => $userProductJourney->journey_id,
            "productSubTypeId" => $userProductJourney->product_sub_type_id,
        ]);
        $commonCotroller->saveQuoteRequestData($request);

        $request->replace([
            "stage" => 2,
            "vehicleRegistrationNo" => $rcNumber,
            "rtoNumber" => isBhSeries($rtoCode) ? null : $rtoCode,
            "rto" => isBhSeries($rtoCode) ? null : $rtoCode,
            "userProductJourneyId" => $userProductJourney->journey_id,
            "vehicleRegisterAt" => isBhSeries($rtoCode) ? null : $rtoCode,
            "enquiryId" => $userProductJourney->journey_id,
            "vehicleRegisterDate" => null,
            "policyExpiryDate" => null,
            "previousInsurerCode" => null,
            "previousInsurer" => null,
            "previousPolicyType" => null,
            "businessType" => null,
            "policyType" => null,
            "previousNcb" => null,
            "applicableNcb" => null,
            "fastlaneJourney" => false
        ]);
        $commonCotroller->saveQuoteRequestData($request);

        $request->replace([
            "stage" => 3,
            "userProductJourneyId" => $userProductJourney->journey_id,
            "enquiryId" => $userProductJourney->journey_id,
            "productSubTypeName" => "GCV",
            "productSubTypeId" => $productSubTypeId,
        ]);
        $commonCotroller->saveQuoteRequestData($request);

        $request->replace([
            "enquiryId" => $userProductJourney->journey_id,
            "registration_no" => $rcNumber,
            "productSubType" => $productSubTypeId,
            "section" => "cv",
            "is_renewal" => "Y",
        ]);
        $vahanResponse = $commonCotroller->getVehicleDetails($request);

        if ($vahanResponse instanceof \Illuminate\Http\JsonResponse) {
            $vahanResponse = json_decode($vahanResponse->getContent(), true);
        }

        if (!($vahanResponse['status'] ?? false)) {
            $error_message = $vahanResponse['msg'] ?? $vahanResponse['message'] ?? null;

            //if below listed error message is present in the config then only throw the error.
            $validations  = config('constants.brokerConstant.ACE_CRM_LEAD_VALIDATION_LIST', '');
            $validations = json_decode($validations, true);

            if (!empty($error_message) && !empty($validations)) {
                foreach ($validations as $validation) {
                    if (strpos(strtoupper($error_message), strtoupper($validation)) !== false) {
                        return response()->json([
                            'status' => false,
                            'message' => $error_message
                        ]);
                    }
                }
            }
        }

        if (!empty($vahanResponse['data']['redirection_data'])) {
            $request->replace([
                "enquiryId" => $userProductJourney->journey_id,
                "vehicleRegistrationNo" => $vahanResponse['data']['additional_details']['vehicleRegistrationNo'],
                "userProductJourneyId" => $userProductJourney->journey_id,
                "corpId" => null,
                "userId" => null,
                "fullName" => $vahanResponse['data']['additional_details']['fullName'],
                "firstName" => $vahanResponse['data']['additional_details']['firstName'],
                "lastName" => $vahanResponse['data']['additional_details']['lastName'],
                "emailId" => $vahanResponse['data']['additional_details']['emailId'],
                "mobileNo" => $vahanResponse['data']['additional_details']['mobileNo'],
                "policyType" => $vahanResponse['data']['additional_details']['policyType'],
                "businessType" => $vahanResponse['data']['additional_details']['businessType'],
                "rto" => $vahanResponse['data']['additional_details']['rto'],
                "manufactureYear" => $vahanResponse['data']['additional_details']['manufactureYear'],
                "version" => $vahanResponse['data']['additional_details']['version'],
                "versionName" => $vahanResponse['data']['additional_details']['versionName'],
                "vehicleRegisterAt" => $vahanResponse['data']['additional_details']["vehicleRegisterAt"],
                "vehicleRegisterDate" => $vahanResponse['data']['additional_details']["vehicleRegisterDate"],
                "vehicleOwnerType" => $vahanResponse['data']['additional_details']["vehicleOwnerType"],
                "hasExpired" => $vahanResponse['data']['additional_details']['hasExpired'],
                "isNcb" => $vahanResponse['data']['additional_details']['isNcb'],
                "isClaim" => $vahanResponse['data']['additional_details']['isClaim'],
                "fuelType" => $vahanResponse['data']['additional_details']['fuelType'],
                "vehicleUsage" =>  $vahanResponse['data']['additional_details']['vehicleUsage'],
                "vehicleLpgCngKitValue" => "",
                "previousInsurer" => $vahanResponse['data']['additional_details']["previousInsurer"],
                "previousInsurerCode" => $vahanResponse['data']['additional_details']['previousInsurerCode'],
                "previousPolicyType" => $vahanResponse['data']['additional_details']["previousPolicyType"],
                "modelName" => $vahanResponse['data']['additional_details']["modelName"],
                "manfactureName" => $vahanResponse['data']['additional_details']["manfactureName"],
                "ownershipChanged" => $vahanResponse['data']['additional_details']["ownershipChanged"],
                "leadJourneyEnd" => $vahanResponse['data']['additional_details']["leadJourneyEnd"],
                "stage" => 11,
                "applicableNcb" => $vahanResponse['data']['additional_details']["applicableNcb"],
                "product_sub_type_id" => $vahanResponse['data']['additional_details']["product_sub_type_id"],
                "manfactureId" => $vahanResponse['data']['additional_details']["manfactureId"],
                "model" => $vahanResponse['data']['additional_details']["model"],
                "previousNcb" => $vahanResponse['data']['additional_details']["previousNcb"],
                "productSubTypeId" => $vahanResponse['data']['additional_details']["productSubTypeId"],
                "engine_number" => $vahanResponse['data']['additional_details']["engine_number"],
                "chassis_number" => $vahanResponse['data']['additional_details']["chassis_number"],
                "pincode" => $vahanResponse['data']['additional_details']["pincode"],
                "oldenquiryId" => $vahanResponse['data']['additional_details']["oldenquiryId"] ?? null
            ]);
            $commonCotroller->saveQuoteRequestData($request);
        }


        $frontEndUrl = config('constants.motorConstant.CV_FRONTEND_URL');

        $quoteUrl = $frontEndUrl . '/quotes?' . http_build_query([
            'enquiry_id' => $userProductJourney->journey_id
        ]);

        $vehicleUrl = $frontEndUrl . '/vehicle-type?' . http_build_query([
            'enquiry_id' => $userProductJourney->journey_id
        ]);

        $journeyStage = [
            'user_product_journey_id' => $userProductJourney->user_product_journey_id,
            'stage' => STAGE_NAMES['LEAD_GENERATION'],
            'quote_url' => $vehicleUrl
        ];
        JourneyStage::updateOrCreate(
            [
                'user_product_journey_id' => $userProductJourney->user_product_journey_id
            ],
            $journeyStage
        );

        updateJourneyStage($journeyStage);

        if ($vahanResponse['status'] ?? false) {
            return redirect($quoteUrl);
        }

        return redirect($vehicleUrl);
    }

    public function onePay(Request $request)
    {
        return $this->generateLead($request, 'ONE_PAY');
    }

    public function bharatBenz(Request $request)
    {
        return $this->generateLead($request, 'BHARAT_BENZ');
    }


    public function onePayleadStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicleRegistrationNumber' => 'nullable|array',
            'enquiryId' => 'nullable|array'
        ]);

        if($validator->fails()){
            return response()->json(['status'=>false,'msg' => $validator->errors()]);
        }

        if (empty($request->vehicleRegistrationNumber) && empty($request->enquiryId)) {
            return response()->json(['status'=>false,'msg' => 'Invalid request']);
        }

        $vehicleRegistrationNumber = $enquiryId = [];

        if (!empty($request->vehicleRegistrationNumber)) {
            $vehicleRegistrationNumber = array_map(function ($rcNumber) {
                return getRegisterNumberWithHyphen($rcNumber);
            }, $request->vehicleRegistrationNumber);
    
            $vehicleRegistrationNumber = array_merge($vehicleRegistrationNumber, $request->vehicleRegistrationNumber);
            $vehicleRegistrationNumber = array_unique($vehicleRegistrationNumber);
        }

        if (!empty($request->enquiryId)) {
            $enquiryId = array_map(function ($traceId) {
                return customDecrypt($traceId);
            }, $request->enquiryId);
        }

        $results = UserProductJourney::join(
            'corporate_vehicles_quotes_request as corp',
            'corp.user_product_journey_id',
            '=',
            'user_product_journey.user_product_journey_id'
        ) ->join(
            'cv_journey_stages as cv',
            'cv.user_product_journey_id',
            '=',
            'corp.user_product_journey_id'
        )
        ->when(!empty($vehicleRegistrationNumber), function($query) use($vehicleRegistrationNumber){
            $query->whereIn('corp.vehicle_registration_no', $vehicleRegistrationNumber);
        })
        ->when(!empty($enquiryId), function($query) use($enquiryId){
            $query->whereIn('user_product_journey.user_product_journey_id', $enquiryId);
        })
        ->where([
            'user_product_journey.lead_source' => 'ONE_PAY'
        ])
        ->select('user_product_journey.*', 'cv.stage', 'corp.vehicle_registration_no')
        ->orderBy('cv.id', 'DESC')
        ->get();

        if (!empty($results) && count($results) > 0) {
            $results = $results->map(function($value) {
                return [
                    'vehicleRegistrationNumber' => $value->vehicle_registration_no,
                    'enquiryId' => $value->journeyId,
                    'status' => strtoupper($value->stage)
                ];
            });

            $results->toArray();

            return response()->json([
                'status' => true,
                'message' => 'Records fetched.',
                'results' => $results
            ], 200);
        }

        return response()->json([
            'status' => false,
            'message' => 'No record found'
        ], 200);
    }
}
