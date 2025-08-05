<?php

namespace App\Http\Controllers;

use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\CvAgentMapping;
use App\Models\FastlaneRequestResponse;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class ProposalVehicleValidation extends Controller
{
    static $blockConstantString = 'Record not found. Block journey.';
    static $blockStatusCode = 102;
    public static function validateVehicle(Request $request)
    {
        $validationService = config('proposalPage.vehicleValidation.serviceType');
        switch ($validationService) {
            case 'fastlane':
                return self::fastlaneValidateVehicle($request);
                break;
            case 'ongrid':
                return self::onGridValidateVehicle($request);
                break;
            default:
                return response()->json([
                    'status' => false,
                    'data' => [
                        'status' => 101
                    ],
                    'msg' => 'No method found for vehicle validation.',
                ]);
                break;
        }
    }

    public static function fastlaneValidateVehicle(Request $request)
    {
        $block_journey = config('proposalPage.vehicleValidation.failureCase.blockJourney') == 'Y';
        $userProductJourneyId = customDecrypt($request->enquiryId);
        if (!empty(config('proposalPage.vehicleValidation.failureCase.blockJourney_userListAllowed'))) {
            $userTypes = explode(',',config('proposalPage.vehicleValidation.failureCase.blockJourney_userListAllowed')); // Allow only these users
            $check=CvAgentMapping::where('user_product_journey_id',$userProductJourneyId)
            ->whereIn('seller_type',$userTypes)
            ->whereNotNull('seller_type')
            ->count();

            if ( $check > 0 )
            {
                $block_journey=false;
            }
        }

        $module_code = config('constants.fastlane.module_code');
        if (!empty($module_code)) {
            $existing_record = DB::table('proposal_vehicle_validation_logs')
                ->select('response')
                ->where('vehicle_reg_no', $request->registration_no)
                ->where('service_type', 'fastlane')
                ->orderBy('id', 'DESC')->first();
        } else {
            $existing_record = DB::table('registration_details')
                ->where('vehicle_reg_no', $request->registration_no)
                ->orderBy('id', 'DESC')
                ->select('vehicle_details as response')
                ->first();
        }
        $isResponseValid = false;
        if (!empty($existing_record)) {
            $tempCurlResponse = json_decode($existing_record->response, true);
            //In Some Broker 'vh_class_desc' tag giving null so assign 'fla_vh_class_desc' value
            if(isset($tempCurlResponse['results'][0]['vehicle']['fla_vh_class_desc']) && empty($tempCurlResponse['results'][0]['vehicle']['vh_class_desc']))
            {
                $tempCurlResponse['results'][0]['vehicle']['vh_class_desc'] = $tempCurlResponse['results'][0]['vehicle']['fla_vh_class_desc'];
            }            
            // If we get the vh_class_desc tag in response then proceed else hit vahan API again.
            $isResponseValid = isset($tempCurlResponse['results'][0]['vehicle']['vh_class_desc']);
            unset($tempCurlResponse);
        }
        if ($isResponseValid) {
            $curlResponse = json_decode($existing_record->response, true);
            if(isset($curlResponse['results'][0]['vehicle']['fla_vh_class_desc']) && empty($curlResponse['results'][0]['vehicle']['vh_class_desc']))
            {
                $curlResponse['results'][0]['vehicle']['vh_class_desc'] = $curlResponse['results'][0]['vehicle']['fla_vh_class_desc'];
            }
        } else {
            $url = config('constants.fastlane.url');
            $build_query = [
                'regn_no' => $request->registration_no,
            ];
            if (!empty($module_code)) {
                $build_query['module_code'] = $module_code;
            }
            $url .= '?' . http_build_query($build_query);
            $username = config('constants.fastlane.username');
            $password = config('constants.fastlane.password');
            // Logs get stored into fastlane_request_response after using getWsData function.
            $get_response = getFastLaneData($url,[
                'enquiryId' => $userProductJourneyId,
                'username' => $username,
                'password' => $password,
                'reg_no' => $request->registration_no,
                'section' => trim($request->section),
                'type' => 'proposal',
            ]);
            $curlResponse = $get_response['response'];
            
            if (empty($curlResponse)) {
                return response()->json([
                    'status' => false,
                    'msg' => $block_journey ? self::$blockConstantString : 'Record not found.',
                    'data' => [
                        'status' => $block_journey ? self::$blockStatusCode : 101,
                    ],
                ]);
            }
            if (stripos($curlResponse, 'Bad credentials') !== false) {
                return response()->json([
                    'status' => false,
                    'msg' => $block_journey ? self::$blockConstantString : 'Record not found.',
                    'data' => [
                        'status' => $block_journey ? self::$blockStatusCode : 101,
                    ],
                ]);
            }
            $curlResponse = json_decode($curlResponse, true);
            if (empty($curlResponse['results'])) {
                $returnResponse = [
                    'status' => false,
                    'msg' => $block_journey ? self::$blockConstantString : 'Record not found.',
                    'data' => [
                        'status' => $block_journey ? self::$blockStatusCode : 101,
                    ],
                ];
                if (!empty($curlResponse['description'] ?? null)) {
                    $returnResponse['overrideMsg'] = $returnResponse['msg'] = $curlResponse['description'];
                }
                return response()->json($returnResponse);
            }
            if(isset($curlResponse['results'][0]['vehicle']['fla_vh_class_desc']) && empty($curlResponse['results'][0]['vehicle']['vh_class_desc']))
            {
                $curlResponse['results'][0]['vehicle']['vh_class_desc'] = $curlResponse['results'][0]['vehicle']['fla_vh_class_desc'];
            }
            if ($curlResponse['status'] != 100) {
                return response()->json([
                    'status' => false,
                    'msg' => $block_journey ? self::$blockConstantString : 'Record not found.',
                    'data' => [
                        'status' => $block_journey ? self::$blockStatusCode : 101,
                    ],
                ]);
            }
            if (isset($curlResponse['results'][0]['vehicle']['vehicle_cd']) && $curlResponse['results'][0]['vehicle']['vehicle_cd'] != null && empty($module_code)) {
                DB::table('registration_details')->insert([
                    'vehicle_reg_no' => $request->registration_no,
                    'vehicle_details' => json_encode($curlResponse),
                    'created_at' => date('Y-m-d H:i:s'),
                    'expiry_date' => isset($curlResponse['results'][0]['insurance']['insurance_upto']) ? Carbon::parse(Str::replace('/', '-', $curlResponse['results'][0]['insurance']['insurance_upto']))->format('Y-m-d') : null,
                ]);
            } else {
                DB::table('proposal_vehicle_validation_logs')->insert([
                    'vehicle_reg_no' => $request->registration_no,
                    'response' => json_encode($curlResponse),
                    'service_type' => 'fastlane',
                    'endpoint_url' => $url,
                    'created_at' => now(),
                ]);
            }
        }
        $fast_lane_product_code = trim($curlResponse['results'][0]['vehicle']['vh_class_desc'] ?? '');
        if(self::isVehicleCategoryBlocked($fast_lane_product_code)) {
            return response()->json([
                'status' => false,
                'msg' => self::$blockConstantString,
                'data' => [
                    'status' => self::$blockStatusCode,
                ],
            ]);
        }

        $ft_product_code = '';
        $vehicle_section = DB::table('fastlane_vehicle_description')->where('description', $fast_lane_product_code)->first();
        if (!empty($vehicle_section)) {
            $ft_product_code = trim($vehicle_section->section);
        } else if (stripos($fast_lane_product_code, 'LMV') !== false) {
            $ft_product_code = 'car';
        } else if (stripos($fast_lane_product_code, 'LPV') !== false) {
            $ft_product_code = 'cv';
        } else if (stripos($fast_lane_product_code, '2W') !== false) {
            $ft_product_code = 'bike';
        }
        $curlResponse['ft_product_code'] = $ft_product_code;
        $curlResponse['status'] = 101;

        // If journey is of car and bike RC number is searched then we'll redirect it to bike page,
        // and again hit the service, for that we need to pass 'status' as 100 - @Amit:08-03-2022
        if (strtolower($ft_product_code) == strtolower($request->section)) {
            $curlResponse['status'] = 100;
        } else {
            $curlResponse['status'] = 100;
            if (empty($ft_product_code)) {
                $row_exists = DB::table('fastlane_vehicle_description')
                    ->where("description", $fast_lane_product_code)
                    ->where('section', null)
                    ->first();
                if (!$row_exists && !empty(trim($fast_lane_product_code))) {
                    DB::table('fastlane_vehicle_description')->insert([
                        "section" => null,
                        "description" => $fast_lane_product_code,
                    ]);
                }
                if ($block_journey) {
                    return response()->json([
                        'status' => false,
                        'msg' => self::$blockConstantString,
                        'data' => [
                            'status' => self::$blockStatusCode,
                        ],
                    ]);
                }
                // If FT code is empty we need to pass status code as 101 only in data array tag - @Amit:18-10-2022
                unset($curlResponse);
                $curlResponse['status'] = 101;
                $curlResponse['description'] = 'Unable to match vehicle description.';
            }
            CorporateVehiclesQuotesRequest::where('user_product_journey_id', $userProductJourneyId)->update([
                'vehicle_registration_no' => $request->registration_no,
            ]);
        }
        $frontend_url = config('constants.motorConstant.' . strtoupper($ft_product_code) . '_FRONTEND_URL');

        if(config('constants.motorConstant.DYNAMIC_FRONTEND_URLS') == 'Y')
        {
            $section = $ft_product_code;
            $frontend_url = getFrontendUrl($section,$userProductJourneyId);
        }

        $curlResponse['redirectionUrl'] = $frontend_url;
        return response()->json([
            'status' => true,
            'msg' => $curlResponse['status'] == 100 ? $curlResponse['description'] : 'Vehicle validation failed.',
            'data' => $curlResponse,
        ]);
    }

    public static function onGridValidateVehicle(Request $request)
    {
        $block_journey = config('proposalPage.vehicleValidation.failureCase.blockJourney') == 'Y';
        $userProductJourneyId = customDecrypt($request->enquiryId);
        $url = null;
        $existing_record = DB::table('proposal_vehicle_validation_logs')
            ->select('response')
            ->where('vehicle_reg_no', $request->registration_no)
            ->where('service_type', 'ongrid')
            ->orderBy('id', 'DESC')->first();
        $isResponseValid = false;
        if(empty($existing_record))
        {
            $existing_record = DB::table('registration_details')
            ->where('vehicle_reg_no', $request->registration_no)
            ->orderBy('id', 'DESC')
            ->select('vehicle_details as response')
            ->first();
            if(!empty($existing_record))
            {
                $response = json_decode($existing_record->response,TRUE);
                $isResponseValid = (isset($response['data']['rc_data']['vehicle_data']['category_description']) && !empty($response['data']['rc_data']['vehicle_data']['category_description']));

            }           
        }else
        {
            $isResponseValid = true;
        }

        if (!empty($existing_record) && $isResponseValid) {
            $curlResponse = json_decode($existing_record->response, true);
        } else {
            $sections = [
                'bike' => 'ongrid-bike',
                'car' => 'ongrid-car',
                'cv' => 'ongrid',
            ];
            $section = $sections[trim($request->section)] ?? 'ongrid';
            $startTime = new DateTime(date('Y-m-d H:i:s'));
            $curlResponse = httpRequest($section, ['rc_number' => $request->registration_no], [], [], [], false);
            $endTime = new DateTime(date('Y-m-d H:i:s'));
            $responseTime = $startTime->diff($endTime);
            $url = $curlResponse["url"];
            $og_response = $curlResponse = $curlResponse['response'];
            FastlaneRequestResponse::insert([
                'enquiry_id' => $userProductJourneyId,
                'request' => $request->registration_no,
                'response' => json_encode($curlResponse),
                'transaction_type' => "Ongrid Service",
                'endpoint_url' => $url,
                'ip_address' => $request->ip(),
                'section' => trim($request->section),
                'response_time' => $responseTime->format('%Y-%m-%d %H:%i:%s'),
                'created_at' => now(),
                'type' => 'proposal',
            ]);
        }
        if (!isset($curlResponse['status']) || $curlResponse['status'] != 200) {
            return response()->json([
                'status' => false,
                'msg' => $block_journey ? self::$blockConstantString : 'Record not found.',
                'data' => [
                    'status' => $block_journey ? self::$blockStatusCode : 101,
                ],
                'response' => $curlResponse,
            ]);
        }
        if (!isset($curlResponse['data']['code']) || $curlResponse['data']['code'] != '1000') {
            return response()->json([
                'status' => false,
                'msg' => $block_journey ? self::$blockConstantString : 'Record not found.',
                'data' => [
                    'status' => $block_journey ? self::$blockStatusCode : 101,
                ],
                'custom_msg' => 'OnGrid API failed with code as ' . ($curlResponse['data']['code'] ?? ''),
                'response' => $curlResponse,
            ]);
        }
        $category_description = trim($curlResponse['data']['rc_data']['vehicle_data']['category_description']);
        if(self::isVehicleCategoryBlocked($category_description)) {
            return response()->json([
                'status' => false,
                'msg' => self::$blockConstantString,
                'data' => [
                    'status' => self::$blockStatusCode,
                ],
            ]);
        }

        $ft_product_code = '';
        $vehicle_section = DB::table('fastlane_vehicle_description')->where('description', $category_description)->first();
        if (!empty($vehicle_section)) {
            $ft_product_code = trim($vehicle_section->section);
        } else if (stripos($category_description ?? '', 'LMV') !== false || stripos($category_description, 'Motor Car') !== false) {
            $ft_product_code = 'car';
        } else if (stripos($category_description ?? '', 'SCOOTER') !== false) {
            $ft_product_code = 'bike';
        }

        if (empty($existing_record) && !empty($ft_product_code)) {
            DB::table('proposal_vehicle_validation_logs')->insert([
                'vehicle_reg_no' => $request->registration_no,
                'response' => json_encode($og_response),
                'service_type' => 'ongrid',
                'endpoint_url' => $url,
                'created_at' => now(),
            ]);
        }

        $curlResponse['ft_product_code'] = $ft_product_code;
        $curlResponse['status'] = 101;

        // If journey is of car and bike RC number is searched then we'll redirect it to bike page,
        // and again hit the service, for that we need to pass 'status' as 100 - @Amit:08-03-2022
        if (strtolower($ft_product_code) == strtolower($request->section)) {
            $curlResponse['status'] = 100;
        } else {
            $curlResponse['status'] = 100;
            if (empty($ft_product_code)) {
                $row_exists = DB::table('fastlane_vehicle_description')
                    ->where("description", $category_description)
                    ->where('section', null)
                    ->first();
                if (!$row_exists && !empty(trim($category_description))) {
                    DB::table('fastlane_vehicle_description')->insert([
                        "section" => null,
                        "description" => $category_description,
                    ]);
                }
                if ($block_journey) {
                    return response()->json([
                        'status' => false,
                        'msg' => self::$blockConstantString,
                        'data' => [
                            'status' => self::$blockStatusCode,
                        ],
                    ]);
                }
                // If FT code is empty we need to pass status code as 101 only in data array tag - @Amit:18-10-2022
                unset($curlResponse);
                $curlResponse['status'] = 101;
                $curlResponse['data']['message'] = 'Unable to match vehicle description.';
            }
            CorporateVehiclesQuotesRequest::where('user_product_journey_id', $userProductJourneyId)->update([
                'vehicle_registration_no' => $request->registration_no,
            ]);
        }
        $frontend_url = config('constants.motorConstant.' . strtoupper($ft_product_code) . '_FRONTEND_URL');
        $curlResponse['redirectionUrl'] = $frontend_url;
        return response()->json([
            'status' => true,
            'msg' => $curlResponse['status'] == 100 ? $curlResponse['data']['message'] : 'Vehicle validation failed.',
            'data' => $curlResponse,
        ]);
    }

    /**
     * This function checks whether the fetched section is in the blocked section or not.
     * @param String $vehicleDescription - The description fetched from the vahan API (ongrid, Fastlane, etc)
     * @return Boolean
     */
    public static function isVehicleCategoryBlocked($vehicleDescription = '')
    {
        $categories = config('proposalPage.vehicleValidation.blockedCategories');
        $blockedVehicles = empty($categories) ? [] : explode(',', $categories);
        return in_array($vehicleDescription, $blockedVehicles);
    }
}
