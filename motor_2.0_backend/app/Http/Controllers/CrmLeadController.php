<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CrmLeadController extends Controller
{
    public function generateLead(Request $request)
    {
        try {
            $validation = Validator::make($request->all(), [
                'xutm' => 'required',
                'crm_lead_id' => 'required',
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validation->errors(),
                ], 422);
            }

            $logsIdLists = [];

            $logsIdLists[] = \App\Models\CrmLeadLog::create([
                'url' => $request->fullUrl(),
                'request' => $request->input(),
                'type' => 'PAYLOAD RECEIVED',
            ])->id;
            
            $userProductJourneyId = null;

            $userProductJourney  = \App\Models\UserProductJourney::where('lead_id', $request->crm_lead_id)
                ->orderBy('user_product_journey_id', 'desc')
                ->get()
                ->first();

            $isJourneyExists = !empty($userProductJourney);

            if (!empty($userProductJourney) && $userProductJourney->status != 'Inactive') {
                $userProductJourneyId = $userProductJourney->user_product_journey_id;
                $journeyStageData = \App\Models\JourneyStage::where('user_product_journey_id', $userProductJourneyId)
                    ->first();

                if (in_array($journeyStageData->stage, [
                    STAGE_NAMES['QUOTE'],
                    STAGE_NAMES['LEAD_GENERATION'],
                ])) {
                    $returnUrl = $journeyStageData->quote_url;
                } else {
                    $returnUrl = $journeyStageData->proposal_url;
                }

                if (in_array(strtolower($journeyStageData->stage), array_map('strtolower', [
                    STAGE_NAMES['POLICY_ISSUED'],
                    STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                    STAGE_NAMES['PAYMENT_SUCCESS'],
                    STAGE_NAMES['PAYMENT_FAILED'],
                    STAGE_NAMES['POLICY_CANCELLED']
                ]))) {
                    return redirect($journeyStageData->proposal_url);
                }

                if (!empty($userProductJourneyId) && !empty($logsIdLists)) {
                    \App\Models\CrmLeadLog::whereIn('id', $logsIdLists)
                        ->update(['user_product_journey_id' => $userProductJourneyId]);
                }
            }

            $tokenValidate = 'ft-crm-token-validate';
            $geLeadDetails = 'ft-crm-lead-details';

            $tokenRequest = [
                'token' => $request->xutm,
                'Id' => $request->crm_lead_id,
            ];

            $ftTokenResponse = httpRequest($tokenValidate, $tokenRequest, save:false);

            $logsIdLists[] = \App\Models\CrmLeadLog::create([
                'user_product_journey_id' => $userProductJourneyId,
                'url' => $ftTokenResponse['url'],
                'request' => $ftTokenResponse['request'],
                'response' => $ftTokenResponse['response'],
                'type' => 'TOKEN VALIDATE',
            ])->id;
            

            if (empty($ftTokenResponse['response']['data'][0]['data']['role'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Role not found in response',
                    'data' => $ftTokenResponse
                ], 404);
            }

            $ftTokenResponse['response'] = $ftTokenResponse['response']['data'][0];

            if (!$ftTokenResponse['response']['status']) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid response from ' . $tokenValidate,
                    'data' => $ftTokenResponse
                ], 404);
            }

            $enquiryData = httpRequest($geLeadDetails, ['Id' => $request->crm_lead_id], save:false);

            $logsIdLists[] = \App\Models\CrmLeadLog::create([
                'user_product_journey_id' => $userProductJourneyId,
                'url' => $enquiryData['url'],
                'request' => $enquiryData['request'],
                'response' => $enquiryData['response'],
                'type' => 'GET ENQUIRY ID DETAILS',
            ])->id;

            if (empty($enquiryData)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid response from ' . $geLeadDetails,
                    'data' => $enquiryData
                ], 404);
            }

            if (!($enquiryData['response']['success'] ?? false)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid response from ' . $geLeadDetails,
                    'data' => $enquiryData
                ], 404);
            }

            $enquiryData['response'] = $enquiryData['response']['data'];

            $rcNumber = \Illuminate\Support\Str::upper($enquiryData['response'][0]['data']['regnNmber']);

            if (empty($rcNumber)) {
                return response()->json([
                    'status' => false,
                    'message' => 'RC Number not found in response',
                    'data' => $enquiryData
                ], 404);
            }

            $rto_code = explode('-', $rcNumber);
            $rto_code = implode('-', [$rto_code[0], $rto_code[1]]);

            $registrationDate = $enquiryData['response'][0]['data']['registrationDate'] ?? null;
            if (!empty($registrationDate)) {
                $registrationDate = str_replace('/', '-', $registrationDate);
                $registrationDate = date('d-m-Y', strtotime($registrationDate));
            }


            if (empty($userProductJourney)) {
                $userProductJourneyData = [
                    'product_sub_type_id' => 6,
                    'user_fname' => $enquiryData['response'][0]['data']['name'] ?? null,
                    'user_lname' => null,
                    'user_email' => $enquiryData['response'][0]['data']['email'] ?? null,
                    'user_mobile' => $enquiryData['response'][0]['data']['mobile'] ?? null,
                    'api_token' => $request->xutm,
                    'lead_id' => $request->crm_lead_id,
                    'lead_source' => 'FT-CRM',
                    'lead_stage_id' => 2
                ];
                $userProductJourney = \App\Models\UserProductJourney::create($userProductJourneyData);
                $userProductJourneyId = $userProductJourney->user_product_journey_id;

                \App\Models\CrmLeadLog::whereIn('id', $logsIdLists)
                    ->update(['user_product_journey_id' => $userProductJourneyId]);
            }

            $sellerResponse = httpRequest('get_seller_details', [
                "email" => $ftTokenResponse['response']['data']['userName']
            ]);

            \App\Models\CrmLeadLog::create([
                'user_product_journey_id' => $userProductJourneyId,
                'url' => $sellerResponse['url'],
                'request' => $sellerResponse['request'],
                'response' => $sellerResponse['response'],
                'type' => 'GET SELLER DETAILS',
            ]);

            $sellerResponse = $sellerResponse['response'];

            if (!$sellerResponse['status'] || $sellerResponse['status'] == 'false') {
                $sellerResponse['email'] = $ftTokenResponse['response']['data']['userName'];
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid response from get_seller_details',
                    'data' => $sellerResponse
                ], 404);
            }

            if (!(in_array($sellerResponse['data']['seller_type'], explode(',', config('ALLOWED_SELLER_TYPES'))))) {
                return response()->json([
                    'status' => false,
                    'message' => ($sellerResponse['data']['seller_type']) . ' Invalid Seller...!',
                    'data' => $sellerResponse
                ], 404);
            }

            $agentData = [
                'agent_id'      => $sellerResponse['data']['seller_id'] ?? null,
                'seller_type'   => $sellerResponse['data']['seller_type'] ?? null,
                'user_name'     => $sellerResponse['data']['user_name'] ?? null,
                'agent_name'    => $sellerResponse['data']['seller_name'] ?? null,
                'agent_mobile'  => $sellerResponse['data']['mobile'] ?? null,
                'agent_email'   => $sellerResponse['data']['email'] ?? null,
                'unique_number' => $sellerResponse['data']['unique_number'] ?? null,
                'aadhar_no'     => $sellerResponse['data']['aadhar_no'] ?? null,
                'pan_no'        => $sellerResponse['data']['pan_no'] ?? null,
                "source"        => $sellerResponse['data']['source'] ?? null,
                'token'         => $ftTokenResponse['response']['data']['userId']
            ];

            \App\Models\CvAgentMapping::updateOrCreate([
                'user_product_journey_id' => $userProductJourney->user_product_journey_id,
            ], $agentData);

            if (!empty($returnUrl)) {
                return redirect($returnUrl);
            }

            $enquiryId = $userProductJourney->journey_id;
            $oldRequest = $request;

            //stage 2
            $request = new \Illuminate\Http\Request([
                "stage" => 1,
                "userProductJourneyId" => $enquiryId,
                "enquiryId" => $enquiryId,
                "productSubTypeId" => "6",
            ]);

            app()->instance('request', $request);

            $controller = new CommonController();
            $controller->saveQuoteRequestData($request);

            $request = new \Illuminate\Http\Request([
                "stage" => 2,
                "vehicleRegistrationNo" => $rcNumber,
                "rtoNumber" => isBhSeries($rto_code) ? null : $rto_code,
                "rto" => isBhSeries($rto_code) ? null : $rto_code,
                "userProductJourneyId" => $enquiryId ?? null,
                "vehicleRegisterAt" => isBhSeries($rto_code) ? null : $rto_code,
                "enquiryId" => $enquiryId ?? null,
                "vehicleRegisterDate" => $registrationDate,
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

            app()->instance('request', $request);

            $controller = new CommonController();
            $controller->saveQuoteRequestData($request);

            //stage 3
            $request = new \Illuminate\Http\Request([
                "stage" => 3,
                "userProductJourneyId" => $enquiryId,
                "enquiryId" => $enquiryId,
                "productSubTypeName" => "TAXI",
                "productSubTypeId" => 6,
            ]);

            app()->instance('request', $request);

            $controller = new CommonController();
            $controller->saveQuoteRequestData($request);

            //check for renewal
            $request = new \Illuminate\Http\Request([
                "enquiryId" => $enquiryId,
                "registration_no" => $rcNumber,
                "productSubType" => 6,
                "section" => "cv",
                "is_renewal" => "Y",
            ]);

            app()->instance('request', $request);

            $controller = new CommonController();
            $vehicleDetails = $controller->getVehicleDetails($request);

            if ($vehicleDetails instanceof \Illuminate\Http\JsonResponse) {
                $vehicleDetails = json_decode($vehicleDetails->getContent(), true);
            }

            //revert the request to original
            $request = $oldRequest;
            app()->instance('request', $request);

            $userProductJourneyId = $userProductJourney->user_product_journey_id;

            \App\Models\UserProductJourney::where(['user_product_journey_id' => $userProductJourneyId])
                ->update(['api_token' => $request->token]);

            $isValidvahan = false;

            if (!($vehicleDetails['status'] ?? true)) {
                $vahanData = $enquiryData['response'][0]['data']['vahanData'] ?? null;

                if (
                    !($vehicleDetails['show_error'] ?? false) &&
                    !empty($vahanData['rc_data'])
                ) {
                    $vahanValidation = AceCrmLeadController::updateVahandetails(
                        $userProductJourneyId,
                        $rcNumber,
                        $vahanData
                    );

                    if ($vahanValidation['status'] ?? false) {
                        $isValidvahan = true;
                    }
                } else {
                    $error_message = $vehicleDetails['msg'] ?? $vehicleDetails['message'] ?? null;

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
            }

            if (config('OVERRIDE_AGENT_DATA_IN_ACE_CRM') == 'Y') {
                \App\Models\CvAgentMapping::updateOrCreate([
                    'user_product_journey_id' => $userProductJourneyId,
                ], $agentData);
            }

            if (
                config('OVERRIDE_USER_PRODUCT_JOURNEY_DATA_IN_ACE_CRM') == 'Y' &&
                !$isJourneyExists && !empty($userProductJourneyData)
            ) {
                unset($userProductJourneyData['product_sub_type_id']);
                \App\Models\UserProductJourney::where('user_product_journey_id', $userProductJourneyId)
                    ->update($userProductJourneyData);
            }

            if (
                isset($vehicleDetails['data']['redirection_data']) &&
                !empty($vehicleDetails['data']['redirection_data'])
            ) {

                $request = new \Illuminate\Http\Request([
                    "enquiryId" => $enquiryId,
                    "vehicleRegistrationNo" => $vehicleDetails['data']['additional_details']['vehicleRegistrationNo'],
                    "userProductJourneyId" => $enquiryId,
                    "corpId" => null,
                    "userId" => null,
                    "fullName" => $vehicleDetails['data']['additional_details']['fullName'],
                    "firstName" => $vehicleDetails['data']['additional_details']['firstName'],
                    "lastName" => $vehicleDetails['data']['additional_details']['lastName'],
                    "emailId" => $vehicleDetails['data']['additional_details']['emailId'],
                    "mobileNo" => $vehicleDetails['data']['additional_details']['mobileNo'],
                    "policyType" => $vehicleDetails['data']['additional_details']['policyType'],
                    "businessType" => $vehicleDetails['data']['additional_details']['businessType'],
                    "rto" => $vehicleDetails['data']['additional_details']['rto'],
                    "manufactureYear" => $vehicleDetails['data']['additional_details']['manufactureYear'],
                    "version" => $vehicleDetails['data']['additional_details']['version'],
                    "versionName" => $vehicleDetails['data']['additional_details']['versionName'],
                    "vehicleRegisterAt" => $vehicleDetails['data']['additional_details']["vehicleRegisterAt"],
                    "vehicleRegisterDate" => $vehicleDetails['data']['additional_details']["vehicleRegisterDate"],
                    "vehicleOwnerType" => $vehicleDetails['data']['additional_details']["vehicleOwnerType"],
                    "hasExpired" => $vehicleDetails['data']['additional_details']['hasExpired'],
                    "isNcb" => $vehicleDetails['data']['additional_details']['isNcb'],
                    "isClaim" => $vehicleDetails['data']['additional_details']['isClaim'],
                    "fuelType" => $vehicleDetails['data']['additional_details']['fuelType'],
                    "vehicleUsage" =>  $vehicleDetails['data']['additional_details']['vehicleUsage'],
                    "vehicleLpgCngKitValue" => "",
                    "previousInsurer" => $vehicleDetails['data']['additional_details']["previousInsurer"],
                    "previousInsurerCode" => $vehicleDetails['data']['additional_details']['previousInsurerCode'],
                    "previousPolicyType" => $vehicleDetails['data']['additional_details']["previousPolicyType"],
                    "modelName" => $vehicleDetails['data']['additional_details']["modelName"],
                    "manfactureName" => $vehicleDetails['data']['additional_details']["manfactureName"],
                    "ownershipChanged" => $vehicleDetails['data']['additional_details']["ownershipChanged"],
                    "leadJourneyEnd" => $vehicleDetails['data']['additional_details']["leadJourneyEnd"],
                    "stage" => 11,
                    "applicableNcb" => $vehicleDetails['data']['additional_details']["applicableNcb"],
                    "product_sub_type_id" => $vehicleDetails['data']['additional_details']["product_sub_type_id"],
                    "manfactureId" => $vehicleDetails['data']['additional_details']["manfactureId"],
                    "model" => $vehicleDetails['data']['additional_details']["model"],
                    "previousNcb" => $vehicleDetails['data']['additional_details']["previousNcb"],
                    "productSubTypeId" => $vehicleDetails['data']['additional_details']["productSubTypeId"],
                    "engine_number" => removeSpecialCharactersFromString($vehicleDetails['data']['additional_details']["engine_number"]),
                    "chassis_number" => removeSpecialCharactersFromString($vehicleDetails['data']['additional_details']["chassis_number"]),
                    "pincode" => $vehicleDetails['data']['additional_details']["pincode"],
                    "oldenquiryId" => $vehicleDetails['data']['additional_details']["oldenquiryId"] ?? null
                ]);

                app()->instance('request', $request);

                $controller = new CommonController();
                $controller->saveQuoteRequestData($request);

                //revert the request to original
                $request = $oldRequest;
                app()->instance('request', $request);
            }

            $queryData = [
                'enquiry_id' => $enquiryId,
                'xutm'       => $request->xutm,
                'lead_id'    => $request->crm_lead_id
            ];

            $journeyStage = [
                'user_product_journey_id' => $userProductJourneyId,
                'stage' => STAGE_NAMES['LEAD_GENERATION'],
                'quote_url' => config('constants.motorConstant.CV_FRONTEND_URL') . '/vehicle-details?' . http_build_query($queryData)
            ];

            \App\Models\JourneyStage::updateOrCreate(
                ['user_product_journey_id' => customDecrypt($enquiryId)],
                $journeyStage
            );
            updateJourneyStage($journeyStage);

            
            if (($vehicleDetails['status'] ?? false) ||  $isValidvahan) {
                $redirectionUrl = redirect(config('constants.motorConstant.CV_FRONTEND_URL') . '/quotes?' . http_build_query($queryData));
            } else {
                $redirectionUrl = redirect(config('constants.motorConstant.CV_FRONTEND_URL') . '/vehicle-details?' . http_build_query($queryData));
            }

            \App\Models\CrmLeadLog::create([
                'user_product_journey_id' => $userProductJourneyId,
                'response' => [
                    'redirection_url' => $redirectionUrl,
                ],
                'type' => 'REDIRECTION URL',
            ]);

            return $redirectionUrl;
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ]);
        }
    }
}
