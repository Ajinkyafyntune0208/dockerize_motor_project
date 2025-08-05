<?php

namespace App\Http\Controllers\VahanService;

use App\Interfaces\VahanServiceInterface;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\FastlaneRequestResponse;
use App\Models\FastlaneVehDescripModel;
use App\Models\MasterProductSubType;
use App\Models\ProposalVehicleValidationLogs;
use App\Models\UserProductJourney;
use App\Models\VahanService;
use App\Models\VahanServiceCredentials;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\ProposalExtraFields;

class OngridServiceController extends VahanServiceController implements VahanServiceInterface
{
    static $blockConstantString = 'Record not found. Block journey.';
    static $blockStatusCode = 102;
    protected $credentials;

    public function __construct()
    {
        $this->setCredentials();
    }

    protected function setCredentials()
    {
        $vahanId = VahanService::where('vahan_service_name_code', 'ongrid')->select('id')->get()->first();

        if (empty($vahanId)) {
            throw new \Exception('Ongrid Vahan Service is not configured.');
        }

        $this->credentials = VahanServiceCredentials::where('vahan_service_id', $vahanId->id)->select('value', 'key')->get()->pluck('value', 'key')->toArray();
    }

    public function getCredential($keyName)
    {
        return $this->credentials[$keyName] ?? null;
    }
    public function getVahanDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => 'required',
            'registration_no' => 'required',
            'productSubType' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        $userProductJourneyId = customDecrypt($request->enquiryId);
        $regi_no = explode('-', $request->registration_no);
        if ($regi_no['0'] == 'DL') {
            $regi_no['1'] = $regi_no['1'] * 1;
        }
        $enquiryId = customDecrypt($request->enquiryId);

        $save_mmv = true;

        if (isset($request->vehicleValidation) && $request->vehicleValidation == 'Y' && config('constants.IS_OLA_BROKER') == 'Y') {
            $save_mmv = false;
        }

        $registration_no = implode('', $regi_no);
        $startTime = new DateTime(date('Y-m-d H:i:s'));
        // For car, bike and CV, ongrid maintains different mapping ids. We'll have to use those accordingly.
        $sections = [
            'bike' => 'bike',
            'car' => 'car',
            'cv' => 'cv',
        ];
        $section = $sections[trim(strtolower($request->section))] ?? 'cv';
        $isTenMonthsLogic = config('constants.isTenMonthsLogicEnabled') == 'Y';
        $query = \Illuminate\Support\Facades\DB::table('registration_details')->where('vehicle_reg_no', $request->registration_no)->where('vehicle_details', 'LIKE', '%Extracted details.%');
        $query->where(function ($subquery) use ($isTenMonthsLogic) {
            $subquery->where('expiry_date', '>=', now()->format('Y-m-d'));
            if ($isTenMonthsLogic) {
                $subquery->orWhere('created_at', '<=', now()->subMonth(10)->format('Y-m-d 00:00:00'));
            }
        });
        $response = $query->latest()->first();
        $array_type = [
            'PCV' => 'cv',
            'GCV' => 'cv',
            'CRP' => 'car',
            'BYK' => 'bike',
        ];
        $headers = [
            "X-API-Key" => $this->getCredential('vahan.ongrid.attribute.x_api_key'),
            "X-Auth-Type" => $this->getCredential('vahan.ongrid.attribute.x_auth_type'),
            "Content-Type" => $this->getCredential('vahan.ongrid.attribute.content_type'),
            "Authorization" => $this->getCredential('vahan.ongrid.attribute.authorization'),
        ];
        $body = [
            "consent" => $this->getCredential('vahan.ongrid.attribute.consent'),
            "extract_mapping" => $this->getCredential('vahan.ongrid.attribute.extract_mapping.' . $section),
            "rc_number" => $registration_no,
        ];
        $url = $this->getCredential("vahan.ongrid.attribute.url");
        if ($response == null) {
            $service = 'online';
            // $response = httpRequest($section, ['rc_number' => $registration_no], [] ,[], [], false);
            $response = httpRequestNormal($url, "POST", $body, [], $headers, [], false);
            $url = $response["url"];
            $response = $response["response"];
            // If journey is of car and user is entering bike RC number then we don't have to save it to DB,
            // because Ongrid maintains different mapping code for seperate vehicle type - 23-06-2022
            if (isset($response['data']['rc_data']['vehicle_data']['custom_data']['version_id'])) {
                $sub_string = Str::substr($response['data']['rc_data']['vehicle_data']['custom_data']['version_id'], 0, 3);
                if (isset($array_type[$sub_string]) && $array_type[$sub_string] == trim($request->section)) {
                    DB::table('registration_details')->insert([
                        'vehicle_reg_no' => $request->registration_no,
                        'vehicle_details' => json_encode($response),
                        'created_at' => now(),
                        'updated_at' => now(),
                        'expiry_date' => $response['data']['rc_data']['insurance_data']['expiry_date'] ?? null,
                    ]);
                }
            }
        } else {
            $service = 'offline';
            $response = json_decode($response->vehicle_details, true);
            // If journey is of car and user is entering bike RC number then we don't have to save it to DB,
            // because Ongrid maintains different mapping code for seperate vehicle type - 23-06-2022
            if (isset($response['data']['rc_data']['vehicle_data']['custom_data']['version_id'])) {
                $sub_string = Str::substr($response['data']['rc_data']['vehicle_data']['custom_data']['version_id'], 0, 3);
                if (isset($array_type[$sub_string]) && $array_type[$sub_string] != trim($request->section)) {
                    // Delete the existing dirty data and again hit the ongrid - 14-07-2022
                    DB::table('registration_details')->where('vehicle_reg_no', $request->registration_no)->delete();
                    $service = 'online';
                    // $response = httpRequest($section, ['rc_number' => $registration_no], [], [], [], false);
                    $response = httpRequestNormal($url, "POST", $body, [], $headers, [], false);
                    $url = $response["url"];
                    $response = $response["response"];
                    // If journey is of car and user is entering bike RC number then we don't have to save it to DB,
                    // because Ongrid maintains different mapping code for seperate vehicle type - 23-06-2022
                    if (isset($response['data']['rc_data']['vehicle_data']['custom_data']['version_id'])) {
                        $sub_string = Str::substr($response['data']['rc_data']['vehicle_data']['custom_data']['version_id'], 0, 3);
                        if (isset($array_type[$sub_string]) && $array_type[$sub_string] == trim($request->section)) {
                            DB::table('registration_details')->insert([
                                'vehicle_reg_no' => $request->registration_no,
                                'vehicle_details' => json_encode($response),
                                'created_at' => now(),
                                'updated_at' => now(),
                                'expiry_date' => $response['data']['rc_data']['insurance_data']['expiry_date'] ?? null,
                            ]);
                        }
                    }
                }
            }
        }
        $og_response = $response;
        $serialNumber = $og_response['data']['rc_data']['owner_data']['serial'] ?? null;

        if ($serialNumber) {
            ProposalExtraFields::updateOrCreate(
                ['enquiry_id' => $userProductJourneyId], 
                [
                    'vahan_serial_number_count' => $serialNumber,
                ]
            );
        }
        $endTime = new DateTime(date('Y-m-d H:i:s'));

        $responseTime = $startTime->diff($endTime);
        if ($service == 'online') {
            $insertedData = FastlaneRequestResponse::create([
                'enquiry_id' => $enquiryId,
                'request' => $request->registration_no,
                'response' => json_encode($og_response),
                'transaction_type' => "Ongrid Service",
                'endpoint_url' => $url,
                'ip_address' => request()->ip(),
                'section' => $request->section,
                'response_time' => $responseTime->format('%H:%i:%s'),
                'created_at' => now(),
                'type' => 'input',
            ]);
        }

        if (!isset($response['data'])) {
            $message = $response['error']['message'] ?? 'Data not found.';
            $this->updateLogData($insertedData->id ?? null, 'Failed', $message);
            return response()->json([
                'status' => false,
                'msg' => $message,
                'data' => [
                    'status' => 101,
                ],
            ]);
        }

        if (!isset($response['data']['rc_data'])) {
            $this->updateLogData($insertedData->id ?? null, 'Failed', 'RC data not found.');
            return response()->json([
                'status' => false,
                'msg' => $response['data']['message'] ?? null,
                'data' => [
                    'status' => 101,
                ],
            ]);
        }

        $date1 = new DateTime($response['data']['rc_data']['issue_date']);
        $date2 = new DateTime();
        $interval = $date1->diff($date2);
        //as per git id removing validation https://github.com/Fyntune/motor_2.0_backend/issues/29828
        // if ($interval->y == 0 && $interval->m < 9) {
        //     $response['status'] = 101;
        //     $this->updateLogData($insertedData->id ?? null, 'Success');
        //     return response()->json([
        //         'status' => false,
        //         'msg' => 'Vehicle (' . $request->registration_no . ') not allowed within 9 Months. Vehicle Registration date is ' . $response['data']['rc_data']['issue_date'] . ' ',
        //         'overrideMsg' => 'Vehicle (' . $request->registration_no . ') not allowed within 9 Months. Vehicle Registration date is ' . $response['data']['rc_data']['issue_date'] . ' ',
        //     ]);
        // }

        $response = $response['data']['rc_data'];
        $sectiontype = 'cv';

        $section_found = false;
        $vehicle_section = FastlaneVehDescripModel::where('description', $response['vehicle_data']['category_description'])->first();
        // Currently Vehicle description is available for Car and bike - 24-06-2022
        // Description added for CV as well - 18-09-2022
        if (!empty($vehicle_section)) {
            $section_found = true;
            $sectiontype = $vehicle_section->section;
            if (in_array(trim($response['vehicle_data']['category_description']), ['Private Service Vehicle']) && ($response['vehicle_data']['category'] ?? '') == 'Individual Use') {
                $sectiontype = 'car';
            }
        } else if (self::findMyWord($response['vehicle_data']['category_description'], 'LMV') || self::findMyWord($response['vehicle_data']['category_description'], 'Motor Car')) {
            $section_found = true;
            $sectiontype = 'car';
        } else if (self::findMyWord($response['vehicle_data']['category_description'], 'SCOOTER') || self::findMyWord($response['vehicle_data']['category_description'], '2WN')) {
            $section_found = true;
            $sectiontype = 'bike';
        }
        // If bike RC number is entered on car page,
        // then show pop-up(pass 100 and parent status as true) based on vehicle description - 07-10-2022
        if ($section_found && $sectiontype != $request->section) {
            $this->updateLogData($insertedData->id ?? null, 'Success');
            return response()->json([
                'status' => true,
                'msg' => 'Mismtached vehicle type',
                'data' => [
                    'ft_product_code' => $sectiontype,
                    'status' => 100,
                    'dataSource' => $service,
                ],
            ]);
        }
        if (!isset($response['vehicle_data']['custom_data']['version_id'])) {
            $this->updateLogData($insertedData->id ?? null, 'Failed', 'Version Id Not Found in Service.');
            return response()->json([
                "data" => [
                    "ft_product_code" => $sectiontype,
                    "status" => 101,
                    'dataSource' => $service,
                ],
                'status' => false,
                'msg' => 'Version Id Not Found in Service...!' ?? null,
            ], 200);
        }

        if (empty($response['vehicle_data']['category_description']) && !$section_found) {
            $version_initial = Str::substr($response['vehicle_data']['custom_data']['version_id'], 0, 3);
            if (isset($array_type[$version_initial])) {
                $sectiontype = $array_type[$version_initial];
            }
        }

        switch (\Illuminate\Support\Str::substr($response['vehicle_data']['custom_data']['version_id'], 0, 3)) {
            case 'PCV':
                # code...
                $type = 'pcv';
                break;
            case 'GCV':
                $type = 'gcv';
                break;
            case 'CRP':
                $type = 'motor';
                break;
            case 'BYK':
                $type = 'bike';
                break;
        }
        $journeyType = empty(request()->journeyType) ? 'ongrid' : base64_decode(request()->journeyType);
        $vehicle_request = new \App\Http\Controllers\EnhanceJourneyController();
        $this->updateLogData($insertedData->id ?? null, 'Success');
        $vehicle_request = $vehicle_request->getVehicleDetails($userProductJourneyId, $request->registration_no, $type, $response, $journeyType, false, $save_mmv);
        $vehicle_request['data']['ft_product_code'] = $sectiontype;
        $vehicle_request['data']['dataSource'] = $service;
        if ($vehicle_request['status']) {
            UserProductJourney::where('user_product_journey_id', $userProductJourneyId)->update([
                'product_sub_type_id' => $vehicle_request['data']['additional_details']['productSubTypeId'],
                'lead_stage_id' => 2,
            ]);

            $vehicle_request['data']['status'] = 100;

            if ($sectiontype == 'car' || $sectiontype == 'bike') {
                $is_sectiontype_enabled = MasterProductSubType::where('product_sub_type_code', strtoupper($sectiontype))
                    ->where('status', 'Active')
                    ->where('parent_id', 0)
                    ->first();

                if (empty($is_sectiontype_enabled)) {
                    $vehicle_request['data']['status'] = 101;
                }
            }
        } else {
            $vehicle_request['data']['status'] = 101;
        }
        return $vehicle_request;
    }

    public function validateVehicleService(Request $request)
    {
        $userProductJourneyId = customDecrypt($request->enquiryId);
        $url = null;
        $existing_record = ProposalVehicleValidationLogs::select('response')
            ->where('vehicle_reg_no', $request->registration_no)
            ->where('service_type', 'ongrid')
            ->orderBy('id', 'DESC')->first();
        $isResponseValid = false;
        if (empty($existing_record)) {
            $existing_record = DB::table('registration_details')
                ->where('vehicle_reg_no', $request->registration_no)
                ->orderBy('id', 'DESC')
                ->select('vehicle_details as response')
                ->first();
            if (!empty($existing_record)) {
                $response = json_decode($existing_record->response, true);
                $isResponseValid = (isset($response['data']['rc_data']['vehicle_data']['category_description']) && !empty($response['data']['rc_data']['vehicle_data']['category_description']));

            }
        } else {
            $isResponseValid = true;
        }
        $isOnlineData = false;
        if (!empty($existing_record) && $isResponseValid) {
            $service = 'offline';
            $curlResponse = json_decode($existing_record->response, true);
        } else {
            $isOnlineData = true;
            $service = 'online';
            $sections = [
                'bike' => 'bike',
                'car' => 'car',
                'cv' => 'cv',
            ];
            $section = $sections[trim(strtolower($request->section))] ?? 'cv';
            $startTime = new DateTime(date('Y-m-d H:i:s'));
            $headers = [
                "X-API-Key" => $this->getCredential('vahan.ongrid.attribute.x_api_key'),
                "X-Auth-Type" => $this->getCredential('vahan.ongrid.attribute.x_auth_type'),
                "Content-Type" => $this->getCredential('vahan.ongrid.attribute.content_type'),
                "Authorization" => $this->getCredential('vahan.ongrid.attribute.authorization'),
            ];
            $body = [
                "consent" => $this->getCredential('vahan.ongrid.attribute.consent'),
                "extract_mapping" => $this->getCredential('vahan.ongrid.attribute.extract_mapping.' . $section),
                "rc_number" => $request->registration_no,
            ];
            $url = $this->getCredential("vahan.ongrid.attribute.url");
            // $curlResponse = httpRequest($section, ['rc_number' => $request->registration_no], [], [], [], false);
            $curlResponse = httpRequestNormal($url, "POST", $body, [], $headers, [], false);
            $endTime = new DateTime(date('Y-m-d H:i:s'));
            $responseTime = $startTime->diff($endTime);
            $url = $curlResponse["url"];
            $og_response = $curlResponse = $curlResponse['response'];
            $insertedData = FastlaneRequestResponse::insert([
                'enquiry_id' => $userProductJourneyId,
                'request' => $request->registration_no,
                'response' => json_encode($curlResponse),
                'transaction_type' => "Ongrid Service",
                'endpoint_url' => $url,
                'ip_address' => request()->ip(),
                'section' => trim($request->section),
                'response_time' => $responseTime->format('%H:%i:%s'),
                'created_at' => now(),
                'type' => 'proposal',
            ]);
        }
        if (!isset($curlResponse['status']) || $curlResponse['status'] != 200) {
            $this->updateLogData($insertedData->id ?? null, 'Failed', 'Record not found.');
            return response()->json([
                'status' => false,
                'msg' => 'Record not found.',
                'data' => [
                    'dataSource' => $service,
                    'status' => 101,
                ],
                'response' => $curlResponse,
            ]);
        }
        if (!isset($curlResponse['data']['code']) || $curlResponse['data']['code'] != '1000') {
            $this->updateLogData($insertedData->id ?? null, 'Failed', 'Record not found.');
            return response()->json([
                'status' => false,
                'msg' => 'Record not found.',
                'data' => [
                    'dataSource' => $service,
                    'status' => 101,
                ],
                'custom_msg' => 'OnGrid API failed with code as ' . ($curlResponse['data']['code'] ?? ''),
                'response' => $curlResponse,
            ]);
        }
        $category_description = trim($curlResponse['data']['rc_data']['vehicle_data']['category_description']);
        if (self::isVehicleCategoryBlocked($category_description)) {
            $this->updateLogData($insertedData->id ?? null, 'Success');
            return response()->json([
                'status' => false,
                'msg' => self::$blockConstantString,
                'data' => [
                    'dataSource' => $service,
                    'status' => self::$blockStatusCode,
                ],
            ]);
        }

        $ft_product_code = '';
        $vehicle_section = FastlaneVehDescripModel::where('description', $category_description)->first();
        if (!empty($vehicle_section)) {
            $ft_product_code = trim($vehicle_section->section);
        } else if (stripos($category_description ?? '', 'LMV') !== false || stripos($category_description, 'Motor Car') !== false) {
            $ft_product_code = 'car';
        } else if (stripos($category_description ?? '', 'SCOOTER') !== false) {
            $ft_product_code = 'bike';
        }

        if (empty($existing_record) && !empty($ft_product_code)) {
            ProposalVehicleValidationLogs::insert([
                'vehicle_reg_no' => $request->registration_no,
                'response' => json_encode($og_response),
                'service_type' => 'ongrid',
                'endpoint_url' => $url,
                'created_at' => now(),
            ]);
        }

        $ongridResponse = $curlResponse;
        $curlResponse['ft_product_code'] = $ft_product_code;
        $curlResponse['status'] = 101;
        $curlResponse['dataSource'] = $service;

        // If journey is of car and bike RC number is searched then we'll redirect it to bike page,
        // and again hit the service, for that we need to pass 'status' as 100 - @Amit:08-03-2022
        if (strtolower($ft_product_code) == strtolower($request->section)) {
            $version_id = $curlResponse['data']['rc_data']['vehicle_data']['custom_data']['version_id'];
            
            if (!empty($version_id)) {
                $ValidateQuoteAndVahanMmv = \App\Helpers\VahanHelper::validateQuouteAndVahanMMv($request, $version_id, $userProductJourneyId);
                if (!$ValidateQuoteAndVahanMmv['status']) {
                    return response()->json($ValidateQuoteAndVahanMmv);
                }
            }
            $curlResponse['status'] = 100;
        } else {
            $curlResponse['status'] = 100;
            if (empty($ft_product_code)) {
                $row_exists = FastlaneVehDescripModel::where("description", $category_description)
                    ->where('section', null)
                    ->first();
                if (!$row_exists && !empty(trim($category_description))) {
                    FastlaneVehDescripModel::insert([
                        "section" => null,
                        "description" => $category_description,
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

        if (self::isFuelTypeMissMatchBlocked($userProductJourneyId, $ongridResponse)) {
            $this->updateLogData($insertedData->id ?? null, 'Success');
            return response()->json([
                'status' => false,
                'msg' => 'Fuel type modification restricted: Fuel type mismatch',
                'data' => [
                    'status' => 101,
                    'overrideMsg' => 'Fuel type modification restricted: Fuel type mismatch',
                ],
            ]);
        }

        $frontend_url = config('constants.motorConstant.' . strtoupper($ft_product_code) . '_FRONTEND_URL');
        $curlResponse['redirectionUrl'] = $frontend_url;
        $this->updateLogData($insertedData->id ?? null, 'Success');
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

    public static function findMyWord($s, $w)
    {
        if (stripos($s, $w) !== false) {
            return true;
        } else {
            return false;
        }
    }

    public static function isFuelTypeMissMatchBlocked($userProductJourneyId, $curlResponse)
    {
        if (config('proposalPage.vehicleValidation.IS_FUEL_TYPE_MISS_MATCH_BLOCKED') != 'Y') {
            return false;
        }
        if (!empty($curlResponse['data']['rc_data']['vehicle_data']['custom_data']['version_id'])) {
            $ongrid_version_id = $curlResponse['data']['rc_data']['vehicle_data']['custom_data']['version_id'];
            $version_id = CorporateVehiclesQuotesRequest::select('version_id')
                ->where('user_product_journey_id', $userProductJourneyId)
                ->first()?->version_id;

            if (!empty($version_id) && $ongrid_version_id != $version_id) {
                $product_sub_type_id = UserProductJourney::select('product_sub_type_id')
                    ->where('user_product_journey_id', $userProductJourneyId)
                    ->first()?->product_sub_type_id;

                if (!empty($product_sub_type_id)) {
                    $ongrid_vehicle_details = get_fyntune_mmv_details(
                        $product_sub_type_id,
                        $curlResponse['data']['rc_data']['vehicle_data']['custom_data']['version_id']
                    );
                    $journey_vehicle_details = get_fyntune_mmv_details($product_sub_type_id, $version_id);

                    $journey_fuel_type = strtoupper($journey_vehicle_details['data']['version']['fuel_type'] ?? null);
                    $ongrid_fuel_type = strtoupper($ongrid_vehicle_details['data']['version']['fuel_type'] ?? null);

                    if (
                        !empty($journey_fuel_type)
                        && !empty($ongrid_fuel_type) && $journey_fuel_type != $ongrid_fuel_type
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
