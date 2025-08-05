<?php

namespace App\Http\Controllers\VahanService;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Interfaces\VahanServiceInterface;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\CvAgentMapping;
use App\Models\FastlaneRequestResponse;
use App\Models\FastlaneVehDescripModel;
use App\Models\MasterProductSubType;
use App\Models\MasterRto;
use App\Models\ProposalVehicleValidationLogs;
use App\Models\QuoteLog;
use App\Models\UserProductJourney;
use App\Models\UserProposal;
use App\Models\VahanService;
use App\Models\VahanServiceCredentials;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\ProposalExtraFields;
use App\Models\Mmvproposaljourneyblocker;

class SurePassServiceController extends VahanServiceController
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
        $vahanId = VahanService::where('vahan_service_name_code', 'surepass')->select('id')->get()->first();

        if (empty($vahanId)) {
            throw new \Exception('surepass Vahan Service is not configured.');
            
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
        if (isset($request->vehicleValidation) && $request->vehicleValidation == 'Y') { //&& config('constants.IS_OLA_BROKER') == 'Y')
            $save_mmv = false;
        }

        $registration_no = implode('', $regi_no);
        $startTime = new DateTime(date('Y-m-d H:i:s'));
        // For car, bike and CV, surepass maintains different mapping ids. We'll have to use those accordingly.
        $sections = [
            'bike' => 'bike',
            'car' => 'car',
            'cv' => 'cv',
        ];
        $section = $sections[trim(strtolower($request->section))] ?? 'cv';
        $isTenMonthsLogic = config('constants.isTenMonthsLogicEnabled') == 'Y';
        $query = \Illuminate\Support\Facades\DB::table('registration_details')->where('vehicle_reg_no', $request->registration_no);
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
            "Content-Type" => $this->getCredential('vahan.surepass.attribute.content_type'),
            "Authorization" => "Bearer " . $this->getCredential('vahan.surepass.attribute.authorization')
        ];
        $body = [
            "id_number" => $registration_no
        ];
        $url = $this->getCredential("vahan.surepass.attribute.url");
        $responseData = null;
        if ($response == null) {
            $service = 'online';
            $response = httpRequestNormal($url, "POST", $body, [], $headers, [], false);
            $url = $response["url"];
            $response = $response["response"];
            // if ($response['status_code'] != 200 && $response['message_code'] != "success") {
            //     return response()->json([
            //         'status' => false,
            //         'msg' => 'Record not found.',
            //         'data' => [
            //             'dataSource' => $service,
            //             'status' => 101
            //         ],
            //         'response' => $response              
            //     ]);
            // }
            if ($response['status_code'] == 200 && $response['message_code'] == "success") {

                $mappingServiceUrl = $this->getCredential('vahan.surepass.attribute.mapping_service_url');
                $vehicle_section = FastlaneVehDescripModel::where('description', $response['data']['vehicle_category'])->first();
                // Currently Vehicle description is available for Car and bike - 24-06-2022
                // Description added for CV as well - 18-09-2022
                if (!empty($vehicle_section)) {
                    $section_found = true;
                    $sectiontype = $vehicle_section->section;
                    if (in_array(trim($response['data']['vehicle_category']), ['Private Service Vehicle']) && ($response['data']['vehicle_category'] ?? '') == 'Individual Use') {
                        $sectiontype = 'car';
                    }
                } else if (self::findMyWord($response['data']['vehicle_category'], 'LMV') || self::findMyWord($response['data']['vehicle_category'], 'Motor Car')) {
                    $section_found = true;
                    $sectiontype = 'car';
                } else if (self::findMyWord($response['data']['vehicle_category'], 'SCOOTER') || self::findMyWord($response['data']['vehicle_category'], '2WN')) {
                    $section_found = true;
                    $sectiontype = 'bike';
                }else if (self::findMyWord($response['data']['vehicle_category'], 'LPV') || self::findMyWord($response['data']['vehicle_category'], '3WT')) {
                    $section_found = true;
                    $sectiontype = 'cv';
                } else if (self::findMyWord($response['data']['vehicle_category'], 'LGV') || self::findMyWord($response['data']['vehicle_category'], 'HGV')) {
                    $section_found = true;
                    $sectiontype = 'cv';
                } else if (self::findMyWord($response['data']['vehicle_category'], 'MGV') || self::findMyWord($response['data']['vehicle_category'], 'LMV')) {
                    $section_found = true;
                    $sectiontype = 'cv';
                }

                CorporateVehiclesQuotesRequest::where('user_product_journey_id', $userProductJourneyId)->update([
                    'vehicle_registration_no' => $request->registration_no,
                ]);

                $frontend_url = config('constants.motorConstant.' . strtoupper($sectiontype) . '_FRONTEND_URL');
                $curlResponse['redirectionUrl'] = $frontend_url;   
                
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
                            'redirectionUrl' => $frontend_url
                        ],
                    ]);
                }
                if ($section == 'bike') {
                    $mappingType = $this->getCredential('vahan.surepass.attribute.mapping_type_bike');
                } elseif ($section == 'car') {
                    $mappingType = $this->getCredential('vahan.surepass.attribute.mapping_type_car');
                }elseif ($section == 'cv') {
                    $mappingType = $this->getCredential('vahan.surepass.attribute.mapping_type_cv');
                } else {
                    return response()->json([
                        'status' => false,
                        'msg' => "vehicle category not found",
                        'data' => [
                            'status' => 101,
                        ],
                    ]);
                }

                $body = [
                    "client_id" => $response['data']['client_id'] ?? "",
                    "mapping_type" => $mappingType,
                    "id_number" => $registration_no,
                ];

                $responseData = httpRequestNormal($mappingServiceUrl, "POST", $body, [], $headers, [], false);
                $responseData = $responseData["response"];
                if ($responseData['status_code'] != 200 && $responseData['message_code'] = "success") {
                    return response()->json([
                        'status' => false,
                        'msg' => $responseData['message'] ?? null,
                        'data' => [
                            'status' => 101,
                        ],
                    ]);
                }
            }


            // If journey is of car and user is entering bike RC number then we don't have to save it to DB,
            // because surepass maintains different mapping code for seperate vehicle type - 23-06-2022
            if (isset($responseData['data']['mapping']['original_data']['version_code'])) {
                $sub_string = Str::substr($responseData['data']['mapping']['original_data']['version_code'], 0, 3);
                if (isset($array_type[$sub_string]) && $array_type[$sub_string] == trim($request->section)) {
                    $finalData = [
                        "rc_full" => $response,
                        "vehicle_mapping" => $responseData
                    ];
                    DB::table('registration_details')->insert([
                        'vehicle_reg_no' => $request->registration_no,
                        'vehicle_details' => json_encode($finalData),
                        'created_at' => now(),
                        'updated_at' => now(),
                        'expiry_date' => $response['data']['insurance_upto'] ?? null,
                    ]);
                }
            }
            else if(isset($responseData['data']['mapping']['original_data']['version_id']))
            {
                 $sub_string = Str::substr($responseData['data']['mapping']['original_data']['version_id'], 0, 3);
                if (isset($array_type[$sub_string]) && $array_type[$sub_string] == trim($request->section)) {
                    $finalData = [
                        "rc_full" => $response,
                        "vehicle_mapping" => $responseData
                    ];
                    DB::table('registration_details')->insert([
                        'vehicle_reg_no' => $request->registration_no,
                        'vehicle_details' => json_encode($finalData),
                        'created_at' => now(),
                        'updated_at' => now(),
                        'expiry_date' => $response['data']['insurance_upto'] ?? null,
                    ]);
                }
            }
        } else {
            $service = 'offline';
            $fullResponse = json_decode($response->vehicle_details, true);
            $responseData = $fullResponse['vehicle_mapping'] ?? [];
            $response = $fullResponse['rc_full'] ?? [];
        }

        $og_response = [
            "rc_full" => $response,
            "vehicle_mapping" => $responseData
        ];
        $serialNumber = $response['data']['owner_number'] ?? null;
        if ($serialNumber) {
            ProposalExtraFields::updateOrCreate(
                ['enquiry_id' =>  $enquiryId], 
                [
                    'vahan_serial_number_count' => $serialNumber,
                ]
            );
        }
        $endTime = new DateTime(date('Y-m-d H:i:s'));

        $responseTime = $startTime->diff($endTime);
        if ($service == 'online') {
            $insertedData = FastlaneRequestResponse::insert([
                'enquiry_id' => $enquiryId,
                'request' => $request->registration_no,
                'response' => json_encode($og_response),
                'transaction_type' => "surepass Service",
                'endpoint_url' => $url,
                'ip_address' => request()->ip(),
                'section' => $request->section,
                'response_time' => $responseTime->format('%H:%i:%s'),
                'created_at' => now(),
                'type' => 'input',
            ]);
        }

        if (!isset($response['data'])) {
            $message = $response['message'] ?? 'Data not found.';
            $this->updateLogData($insertedData->id ?? null, 'Failed', $message);
            return response()->json([
                'status' => false,
                'msg' => $message,
                'data' => [
                    'status' => 101,
                ],
            ]);
        }

        if (empty($responseData['data']) && ($responseData['status_code'] ?? "") != 200) {
            $this->updateLogData($insertedData->id ?? null, 'Failed', 'RC data not found.');
            return response()->json([
                'status' => false,
                'msg' => $response['data']['message'] ?? null,
                'data' => [
                    'status' => 101,
                ],
            ]);
        } 

        // if (!isset($responseData['data']) && ($responseData['status_code'] == 200)) {
        //     $this->updateLogData($insertedData->id ?? null, 'Failed', 'RC data not found.');
        //     return response()->json([
        //         'status' => false,
        //         'msg' => $response['data']['message'] ?? null,
        //         'data' => [
        //             'status' => 101,
        //         ],
        //     ]);
        // }

        $date1 = new DateTime($response['data']['registration_date']);
        $date2 = new DateTime();
        $interval = $date1->diff($date2);
        //as per git id removing validation https://github.com/Fyntune/motor_2.0_backend/issues/29828
        // if ($interval->y == 0 && $interval->m < 9) {
        //     $response['status'] = 101;
        //     $this->updateLogData($insertedData->id ?? null, 'Success');
        //     return response()->json([
        //         'status' => false,
        //         'msg' => 'Vehicle (' . $request->registration_no . ') not allowed within 9 Months. Vehicle Registration date is ' . $response['data']['latest_by'] . ' ',
        //         'overrideMsg' => 'Vehicle (' . $request->registration_no . ') not allowed within 9 Months. Vehicle Registration date is ' . $response['data']['latest_by'] . ' ',
        //     ]);
        // }

        $response = $response['data'];
        $sectiontype = 'cv';

        $section_found = false;
        if (!isset($responseData['data']['mapping']['original_data']['version_code']) && !isset($responseData['data']['mapping']['original_data']['version_id'])) {
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

        if (empty($response['data']['vehicle_category']) && !$section_found) {
            $version_initial = isset($responseData['data']['mapping']['original_data']['version_code']) ? Str::substr($responseData['data']['mapping']['original_data']['version_code'], 0, 3) : Str::substr($responseData['data']['mapping']['original_data']['version_id'], 0, 3);
            if (isset($array_type[$version_initial])) {
                $sectiontype = $array_type[$version_initial];
            }
        }

        if(isset($responseData['data']['mapping']['original_data']['version_code']))
        {
        switch (\Illuminate\Support\Str::substr($responseData['data']['mapping']['original_data']['version_code'], 0, 3)) {
            case 'PCV':
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
    }
        else if($responseData['data']['mapping']['original_data']['version_id'])
        {
            switch (\Illuminate\Support\Str::substr($responseData['data']['mapping']['original_data']['version_id'], 0, 3)) {
            case 'PCV':
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
    }

        $journeyType = empty(request()->journeyType) ? 'surepass' : base64_decode(request()->journeyType);

        // $vehicle_request = new \App\Http\Controllers\EnhanceJourneyController();

        $this->updateLogData($insertedData->id ?? null, 'Success');


        $vehicle_request = self::getVehicleDetails($userProductJourneyId, $request->registration_no, $type, $response, $responseData, $journeyType, false, $save_mmv);
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
            else if($sectiontype == 'cv')
            {
                $is_sectiontype_enabled = MasterProductSubType::where('product_sub_type_code', $version_initial)
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
            ->where('service_type', 'surepass')
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
                $isResponseValid = (isset($response['rc_full']['data']['vehicle_category_description']) && !empty($response['rc_full']['data']['vehicle_category_description']));

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
                "Content-Type" => $this->getCredential('vahan.surepass.attribute.content_type'),
                "Authorization" => "Bearer " . $this->getCredential('vahan.surepass.attribute.authorization')
            ];
            $regi_no = explode('-', $request->registration_no);
            if ($regi_no['0'] == 'DL') {
                $regi_no['1'] = $regi_no['1'] * 1;
            }
            $registration_no = implode('', $regi_no);
            $body = [
                "id_number" => $registration_no
            ];
            $url = $this->getCredential("vahan.surepass.attribute.url");
            $curlResponse = httpRequestNormal($url, "POST", $body, [], $headers, [], false);
            $endTime = new DateTime(date('Y-m-d H:i:s'));
            $responseTime = $startTime->diff($endTime);
            $url = $curlResponse["url"];
            $response = $curlResponse["response"];

            //second service get_vehicle_mapping
            if ($response['status_code'] == 200 && $response['message_code'] == "success") {
                $mappingServiceUrl = $this->getCredential('vahan.surepass.attribute.mapping_service_url');
                $vehicle_section = FastlaneVehDescripModel::where('description', $response['data']['vehicle_category'])->first();
                if (!empty($vehicle_section)) {
                    $section_found = true;
                    $sectiontype = $vehicle_section->section;
                    if (in_array(trim($response['data']['vehicle_category']), ['Private Service Vehicle']) && ($response['data']['vehicle_category'] ?? '') == 'Individual Use') {
                        $sectiontype = 'car';
                    }
                } else if (self::findMyWord($response['data']['vehicle_category'], 'LMV') || self::findMyWord($response['data']['vehicle_category'], 'Motor Car')) {
                    $section_found = true;
                    $sectiontype = 'car';
                } else if (self::findMyWord($response['data']['vehicle_category'], 'SCOOTER') || self::findMyWord($response['data']['vehicle_category'], '2WN')) {
                    $section_found = true;
                    $sectiontype = 'bike';
                }
                else if (self::findMyWord($response['data']['vehicle_category'], 'LPV') || self::findMyWord($response['data']['vehicle_category'], '3WT')) {
                    $section_found = true;
                    $sectiontype = 'cv';
                } else if (self::findMyWord($response['data']['vehicle_category'], 'LGV') || self::findMyWord($response['data']['vehicle_category'], 'HGV')) {
                    $section_found = true;
                    $sectiontype = 'cv';
                } else if (self::findMyWord($response['data']['vehicle_category'], 'MGV') || self::findMyWord($response['data']['vehicle_category'], 'LMV')) {
                    $section_found = true;
                    $sectiontype = 'cv';
                }

                CorporateVehiclesQuotesRequest::where('user_product_journey_id', $userProductJourneyId)->update([
                    'vehicle_registration_no' => $request->registration_no,
                ]);

                $frontend_url = config('constants.motorConstant.' . strtoupper($sectiontype) . '_FRONTEND_URL');
                $curlResponse['redirectionUrl'] = $frontend_url;

                if ($section_found && $sectiontype != $request->section) {
                    $this->updateLogData($insertedData->id ?? null, 'Success');
                    return response()->json([
                        'status' => true,
                        'msg' => 'Mismtached vehicle type',
                        'data' => [
                            'ft_product_code' => $sectiontype,
                            'status' => 100,
                            'dataSource' => $service,
                            'redirectionUrl' => $frontend_url
                        ],
                    ]);
                }
                if ($section == 'bike') {
                    $mappingType = $this->getCredential('vahan.surepass.attribute.mapping_type_bike');
                } elseif ($section == 'car') {
                    $mappingType = $this->getCredential('vahan.surepass.attribute.mapping_type_car');
                } elseif ($section == 'cv') {
                    $mappingType = $this->getCredential('vahan.surepass.attribute.mapping_type_cv');
                }else {
                    return response()->json([
                        'status' => false,
                        'msg' => "vehicle category not found",
                        'data' => [
                            'status' => 101,
                        ],
                    ]);
                }

                $body = [
                    "client_id" => $response['data']['client_id'] ?? "",
                    "mapping_type" => $mappingType,
                    "id_number" => $registration_no,
                ];

                $responseData = httpRequestNormal($mappingServiceUrl, "POST", $body, [], $headers, [], false);
                $responseData = $responseData["response"];
                if ($responseData['status_code'] != 200 && $responseData['message_code'] = "success") {
                    return response()->json([
                        'status' => false,
                        'msg' => $responseData['message'] ?? null,
                        'data' => [
                            'status' => 101,
                        ],
                    ]);
                }
            }

            $curlResponse = [
                "rc_full" => $response,
                "vehicle_mapping" => $responseData
            ];

            $insertedData = FastlaneRequestResponse::insert([
                'enquiry_id' => $userProductJourneyId,
                'request' => $request->registration_no,
                'response' => json_encode($curlResponse),
                'transaction_type' => "Surepass Service",
                'endpoint_url' => $url,
                'ip_address' => request()->ip(),
                'section' => trim($request->section),
                'response_time' => $responseTime->format('%H:%i:%s'),
                'created_at' => now(),
                'type' => 'proposal',
            ]);
        }
        if (!isset($curlResponse['rc_full']['status_code']) && !isset($curlResponse['vehicle_mapping']['status_code']) || ($curlResponse['rc_full']['status_code'] && $curlResponse['vehicle_mapping']['status_code']) != 200) {
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
        if (!isset($curlResponse['rc_full']['message_code']) && !isset($curlResponse['vehicle_mapping']['message_code']) || ($curlResponse['rc_full']['message_code'] && $curlResponse['vehicle_mapping']['message_code'] ) != 'success') {
            $this->updateLogData($insertedData->id ?? null, 'Failed', 'Record not found.');
            return response()->json([
                'status' => false,
                'msg' => 'Record not found.',
                'data' => [
                    'dataSource' => $service,
                    'status' => 101,
                ],
                'custom_msg' => 'Surepass API failed with code as ' . ($curlResponse['rc_full']['message_code'] ?? $curlResponse['vehicle_mapping']['message_code'] ?? ''),
                'response' => $og_response ?? $curlResponse,
            ]);
        }
        $category_description = trim($response['data']['vehicle_category_description'] ?? $curlResponse['rc_full']['data']['vehicle_category_description'] );
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
        }else if (stripos($category_description ?? '', 'LPV') !== false || stripos($category_description, '3WT') !== false) {
            $ft_product_code = 'cv';
        }else if (stripos($category_description ?? '', 'LGV') !== false || stripos($category_description, 'HGV') !== false) {
            $ft_product_code = 'cv';
        }else if (stripos($category_description ?? '', 'MGV') !== false || stripos($category_description, 'LMV') !== false) {
            $ft_product_code = 'cv';
        }

        if (empty($existing_record) && !empty($ft_product_code)) {
            ProposalVehicleValidationLogs::insert([
                'vehicle_reg_no' => $request->registration_no,
                'response' => json_encode($curlResponse),
                'service_type' => 'surepass',
                'endpoint_url' => $url,
                'created_at' => now(),
            ]);
        }

        $surepassResponse = $curlResponse;
        $curlResponse['ft_product_code'] = $ft_product_code;
        $curlResponse['status'] = 101;
        $curlResponse['dataSource'] = $service;

        // If journey is of car and bike RC number is searched then we'll redirect it to bike page,
        // and again hit the service, for that we need to pass 'status' as 100 - @Amit:08-03-2022
        if (strtolower($ft_product_code) == strtolower($request->section)) {
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
        if ($service == "offline"){ 
            $responseData = $curlResponse['vehicle_mapping'] ?? [];
            $response = $curlResponse['rc_full'] ?? [];
        }

        if (self::isFuelTypeMissMatchBlocked($userProductJourneyId, $surepassResponse, $responseData)) {
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

        if(!empty($responseData['data']['mapping']['original_data']['version_code']) && strtolower($ft_product_code) == strtolower($request->section)){
            $ValidateQuoteAndVahanMmv = \App\Helpers\VahanHelper::validateQuouteAndVahanMMv($request, $responseData['data']['mapping']['original_data']['version_code'], $userProductJourneyId);
            if(!$ValidateQuoteAndVahanMmv['status']){
                return response()->json($ValidateQuoteAndVahanMmv);
            }
        }

        // if (config('proposalPage.vehicleValidation.mmvValidationQuoteProposal')=='Y' && isset($responseData['data']['mapping']['original_data']['version_code']) && !empty($responseData['data']['mapping']['original_data']['version_code'])){
        //     $proposal_version_code = $curlResponse['vehicle_mapping']['data']['mapping']['original_data']['version_code'];  // Surepass API Response
        //     $proposal_mmv_details = get_fyntune_mmv_details(Str::substr($proposal_version_code, 0, 3), $proposal_version_code);
                
        //     $quote_vehicle_data = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $userProductJourneyId)->first();
        //     $quote_mmv_details = get_fyntune_mmv_details(Str::substr($quote_vehicle_data['version_id'], 0, 3), $quote_vehicle_data['version_id']);

        //     $quote_mmv_details = $quote_mmv_details['data']; // Quote MMV details Data
        //     $proposal_mmv_details = $proposal_mmv_details['data']; // Quote MMV details Data

        //     $quote_manufacturer = strtolower($quote_mmv_details['manufacturer']['manf_name']);
        //     $quote_model = strtolower($quote_mmv_details['model']['model_name']);
        //     $quote_fuel_type = strtolower($quote_mmv_details['version']['fuel_type']);
        //     $quote_cc = strtolower($quote_mmv_details['version']['cubic_capacity']);

        //     $proposal_manufacturer = strtolower($proposal_mmv_details['manufacturer']['manf_name']);
        //     $proposal_model = strtolower($proposal_mmv_details['model']['model_name']);
        //     $proposal_fuel_type = strtolower($proposal_mmv_details['version']['fuel_type']);
        //     $proposal_cc = strtolower($proposal_mmv_details['version']['cubic_capacity']);

        //     if(strtolower($ft_product_code) == strtolower($request->section)){
        //         $allowMakeMismatch = self::isAllow('Make');
        //         if($quote_manufacturer != $proposal_manufacturer && $allowMakeMismatch){                     
        //             return response()->json([
        //                 'status' => false,
        //                 'overrideMsg' => "Trace ID - ".$request->enquiryId." Vehicle Make mismatch with Vahan. Policy issuance is not allowed",
        //                 'data' => [
        //                     'status' => 101
        //                 ]
        //             ]);
        //         }
        //         $allowModelMismatch = self::isAllow('Model');
        //         if($quote_model != $proposal_model && $allowModelMismatch){                    
        //             return response()->json([
        //                 'status' => false,
        //                 'overrideMsg' => "Trace ID - ".$request->enquiryId." Vehicle Model mismatch with Vahan. Policy issuance is not allowed",
        //                 'data' => [
        //                     'status' => 101
        //                 ],
        //             ]);
        //         }
        //         $allowFuelTypeMismatch = self::isAllow('Fuel');
        //         if($quote_fuel_type != $proposal_fuel_type && $allowFuelTypeMismatch){                    
        //             return response()->json([
        //                 'status' => false,
        //                 'overrideMsg' => "Trace ID - ".$request->enquiryId." Vehicle Fuel Type mismatch with Vahan. Policy issuance is not allowed",
        //                 'data' => [
        //                     'status' => 101
        //                 ],
        //             ]);
        //         }
        //     }
        // }

        $frontend_url = config('constants.motorConstant.' . strtoupper($ft_product_code) . '_FRONTEND_URL');
        $curlResponse['redirectionUrl'] = $frontend_url;
        $this->updateLogData($insertedData->id ?? null, 'Success');
        return response()->json([
            'status' => true,
            'msg' => $response['status_code']  ? $response['message']  : 'Vehicle validation failed.',
            'data' => $curlResponse,
        ]);
    }


    public static function isAllow($type)
    {
        $allow = Mmvproposaljourneyblocker::where('name', $type)->first();
        if ($allow->value == 'Y') {
            return true;
        } else {
            return false;
        }
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

    public static function isFuelTypeMissMatchBlocked($userProductJourneyId, $curlResponse, $curlResponseMmv)
    {
        if (config('proposalPage.vehicleValidation.IS_FUEL_TYPE_MISS_MATCH_BLOCKED') != 'Y') {
            return false;
        }
        if (!empty($curlResponseMmv['data']['mapping']['original_data']['version_code'])) {
            $surepass_version_id = $curlResponseMmv['data']['mapping']['original_data']['version_code'];
            $version_id = CorporateVehiclesQuotesRequest::select('version_id')
                ->where('user_product_journey_id', $userProductJourneyId)
                ->first()?->version_id;

            if (!empty($version_id) && $surepass_version_id != $version_id) {
                $product_sub_type_id = UserProductJourney::select('product_sub_type_id')
                    ->where('user_product_journey_id', $userProductJourneyId)
                    ->first()?->product_sub_type_id;

                if (!empty($product_sub_type_id)) {
                    $surepass_vehicle_details = get_fyntune_mmv_details(
                        $product_sub_type_id,
                        $curlResponseMmv['data']['mapping']['original_data']['version_code']
                    );
                    $journey_vehicle_details = get_fyntune_mmv_details($product_sub_type_id, $version_id);
                    $journey_fuel_type = strtoupper($journey_vehicle_details['data']['version']['fuel_type'] ?? null);
                    $surepass_fuel_type = strtoupper($surepass_vehicle_details['data']['version']['fuel_type'] ?? null);
                    if (
                        !empty($journey_fuel_type)
                        && !empty($surepass_fuel_type) && $journey_fuel_type != $surepass_fuel_type
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function getVehicleDetails($enquiryId, $new_rc_number, $type, $response, $responseData, $journey_type, $inapp = false, $save_mmv = true)
    {
        try {
            $version_code = '';
            $version_id = $responseData['data']['mapping']['original_data']['version_code'] ?? $responseData['data']['mapping']['original_data']['version_id'];
            $env = config('app.env');
            if ($env == 'local') {
                $env_folder = 'uat';
            } else if ($env == 'test' || $env == 'live') {
                $env_folder = 'production';
            }
            $product = $type;
            $product = $product == 'car' ? 'motor' : $product;
            $path = 'mmv_masters/' . $env_folder . '/';
            $file_name = $path . $product . '_model_version.json';
            //$data = json_decode(file_get_contents($file_name), true);
            $data = json_decode(\Illuminate\Support\Facades\Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($file_name), true);
            if ($data) {
                foreach ($data as $value) {
                    if ($value['version_id'] == $version_id) {
                        $version_code = $value['version_id'];
                    }
                }
            }
            if (empty($version_code)) {
                return [
                    'status' => false,
                    'message' => 'version id not found in file  for ' . $version_id,
                ];
            }
            $registration_date = date('d-m-Y', strtotime(str_replace('/', '-', $response['registration_date'])));
            if (isset($response['insurance_upto']) && !empty($response['insurance_upto'])) {
                $policy_expiry = date('d-m-Y', strtotime(str_replace('/', '-', $response['insurance_upto'])));
                $date1 = new \DateTime($registration_date);
                $date2 = new \DateTime(date('d-m-Y'));
                $interval = $date1->diff($date2);
                $car_age = (($interval->y * 12) + $interval->m) + 1; // In Months
                $previous_ncb = 0;
                if ($car_age < 20) {
                    $previous_ncb = 0;
                    $applicable_ncb = 20;
                } elseif ($car_age < 32) {
                    $previous_ncb = 20;
                    $applicable_ncb = 25;
                } elseif ($car_age < 44) {
                    $previous_ncb = 25;
                    $applicable_ncb = 35;
                } elseif ($car_age < 56) {
                    $previous_ncb = 35;
                    $applicable_ncb = 45;
                } elseif ($car_age < 68) {
                    $previous_ncb = 45;
                    $applicable_ncb = 50;
                } else {
                    $previous_ncb = 50;
                    $applicable_ncb = 50;
                }
            } else {
                $policy_expiry = null;
                $previous_ncb = null;
                $applicable_ncb = null;
            }
            $invoiceDate = null;
            if (\Illuminate\Support\Str::contains($response['manufacturing_date_formatted'] ?? '', '-')) {
                $manf_year = explode('-', $response['manufacturing_date_formatted']);
                //$manf_year = date('m', strtotime($registration_date)) . '-' . $manf_year[0];
                $manf_year = $manf_year[1].'-'.$manf_year[0];
                // $invoiceDate = '01-'.$manf_year;
            } else {
                $manf_year = date('m-Y', strtotime($registration_date));
            }
            $invoiceDate = $registration_date;
            $reg_no = explode('-', $new_rc_number);
            if ($reg_no[1] < 10 && $reg_no[0] == 'DL' && $journey_type != 'surepass') {
                $reg_no[1] = '0' . $reg_no[1];
            }
            $rto_code = implode('-', [$reg_no[0], $reg_no[1]]);
            $rto_name = MasterRto::where('rto_code', $rto_code)->pluck('rto_name')->first();
            // Fetch company alias from
            $previous_insurer = $previous_insurer_code = NULL;

            $expiry_date = \Carbon\Carbon::parse($policy_expiry);
            $today_date = now()->subDay(1);
            if ($expiry_date < $today_date) {
                $businessType = 'breakin';
                // $applicable_ncb = 0;
                $diff = $expiry_date->diffInDays(now());
                if ($diff > 90) {
                    $previous_ncb = 0;
                    $applicable_ncb = 0;
                }
            } else {
                $businessType = 'rollover';
            }
            // If policy expiry date is more than 45 days from the todays date,
            // then pass it as blank so that pop-up will open in frontend.
            if ($expiry_date->diffInDays(now()) > 45) {
                $policy_expiry = '';
            }
            //$fast_lane_product_code = $response['vehicle_data']['category'] ?? '';
            $ft_product_code = $cv_type = '';
            $vehicle_section = DB::table('fastlane_vehicle_description')->where('description', $response['vehicle_category_description'])->first();
            // Currently Vehicle description is available for Car and bike - 24-06-2022 
            if (!empty($vehicle_section)) {
                $ft_product_code = $vehicle_section->section;
                $ft_product_code == 'cv' ? $cv_type = 'cv' : $cv_type = '';
            } else if (isset($response['vehicle_category_description']) && (stripos($response['vehicle_category_description'], 'LMV') !== false) || (stripos($response['vehicle_category_description'], 'Motor Car') !== false)) {
                $ft_product_code = 'car';
            } else if (isset($response['vehicle_category_description']) && stripos($response['vehicle_category_description'], 'SCOOTER') !== false) {
                $ft_product_code = 'bike';
            }
            if (isset($response['vehicle_category_description'])) { 
                $ft_product_code = $response['vehicle_category_description'];
            }
            if ($ft_product_code == 'car' || $ft_product_code == 'TAXI') {
                $od_compare_date = now()->subYear(2)->subDay(180);
            } elseif ($ft_product_code == 'bike') {
                $od_compare_date = now()->subYear(4)->subDay(180);
            }

            if (in_array($ft_product_code, ['car', 'bike'])) {
                $policy_type = Carbon::parse($registration_date) > $od_compare_date ? 'own_damage' : 'comprehensive';
            } else {
                $policy_type = 'comprehensive';
            }

            if (in_array($type, ['pcv', 'gcv'])) {
                $policy_type = 'comprehensive';
            }

            if ($policy_type == 'own_damage') {
                $policy_expiry = '';
            }
            $producttype = [
                '1' => 'motor',
                '2' => 'bike',
                '6' => 'pcv',
                '9' => 'gcv',
                '13' => 'gcv',
                '14' => 'gcv',
                '15' => 'gcv',
                '16' => 'gcv',
            ];
            $cv_sections = [
                "AUTO-RICKSHAW" => "5",
                "E-RICKSHAW" => "11",
                "AUTO RICKSHAW" => "5",
                "TAXI" => "6",
                "Three Wheeler (Passenger)" => "5",
                "PASSENGER-BUS" => "7",
                "PICK UP/DELIVERY/REFRIGERATED VAN" => "9",
                "SCHOOL-BUS" => "10",
                "ELECTRIC-RICKSHAW" => "11",
                "TEMPO-TRAVELLER" => "12",
                "DUMPER/TIPPER" => "13",
                "TRUCK" => "14",
                "TRACTOR" => "15",
                "TANKER/BULKER" => "16",
                "AGRICULTURAL-TRACTOR" => "17",
                "MISCELLANEOUS-CLASS" => "18"
            ];
            // Ola is live with Taxi, we don't want to stop that code flow.
            if ($cv_type == 'cv' && $ft_product_code != 'TAXI' && isset($cv_sections[$ft_product_code])) {
                // $product_sub_type_id = array_search($ft_product_code, $cv_sections);
                $product_sub_type_id = $cv_sections[$ft_product_code];
            } else {
                $product_sub_type_id = array_search($product, $producttype);
            }
            $curlResponse = [];

            $ncb_previous_ic_popup_disabled = config('NCB_PREVIOUS_IC_POPUP_DISABLED') == 'Y'; //to disable NCB popup
            $ncb_previous_ic_popup_seller_types = [];

            $cv_agent_mappings = CvAgentMapping::where('user_product_journey_id', $enquiryId)->first();

            if (config('NCB_PREVIOUS_IC_POPUP_SELLER_TYPES')) {
                $ncb_previous_ic_popup_seller_types = explode(',', config('NCB_PREVIOUS_IC_POPUP_SELLER_TYPES'));

                if (! empty($cv_agent_mappings->user_id)) {
                    array_push($ncb_previous_ic_popup_seller_types, NULL);
                }
            }

            if (isset($response['insurance_company']) && !empty($response['insurance_company'])) {
                $fyntune_ic = DB::table('fastlane_previous_ic_mapping as m')->whereRaw('? LIKE CONCAT("%", m.identifier, "%")', $response['insurance_company'])->first();
                if ((! $ncb_previous_ic_popup_disabled || ($ncb_previous_ic_popup_disabled && ! in_array($cv_agent_mappings?->seller_type, $ncb_previous_ic_popup_seller_types))) && $fyntune_ic) {
                    $previous_insurer = $fyntune_ic->company_name;
                    $previous_insurer_code = $fyntune_ic->company_alias;
                }
            }

            $mmv_details = get_fyntune_mmv_details($product_sub_type_id, $version_code);
            if (!$mmv_details['status']) {
                return [
                    'status' => false,
                    'message' => 'MMV details not found. - ' . $version_code,
                ];
            }
            $mmv_details = $mmv_details['data'];
            // if 3 wheeler variants are blocked then don't show variants
            if (($mmv_details['version']['no_of_wheels'] ?? '0') == '3' && config('IS_3_WHEELER_BLOCKED') == 'Y') {
                return [
                    'status' => false,
                    'message' => '3 Wheeler quotes are blocked - ' . $version_code,
                ];
            }

            if (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy' && strtolower($mmv_details['manufacturer']['manf_name'] ?? '') == 'morris garages') {
                return [
                    'status' => false,
                    'message' => 'Morris Garages vehicle are not allowed.',
                ];
            }
            $previous_insurance = '';
            if (!$inapp || empty($policy_expiry)) {
                if ($previous_insurer) {
                    $previous_insurance = $previous_insurer;
                } else {
                    $previous_insurance = $response['insurance_company'] ?? '';
                }
            }
            $previous_policy_type = "Comprehensive";
            if(config('newBreakinLogic.Enabled') == 'Y')
            {
                $manufactureDate = '01-' . $manf_year;
                $newBreakinData = $this->newBreakinLogicHandle($registration_date,$manufactureDate);
                if(!empty($newBreakinData))
                {
                    extract($newBreakinData);
                    $previous_insurance = $previous_insurer;
                }
                else
                {
                    $previous_policy_type = "Comprehensive";
                }
            }
           


            $corporate_vehicles_quote_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();
            CorporateVehiclesQuotesRequest::updateOrCreate(
                [
                    'user_product_journey_id' => $enquiryId
                ],
                [
                    'version_id' => $save_mmv ? $version_code : $corporate_vehicles_quote_request->version_id,
                    'user_product_journey_id' => $enquiryId,
                    'policy_type' => $policy_type,
                    'product_id' => $product_sub_type_id,
                    'business_type' => $businessType, #'rollover',
                    'vehicle_register_date' => $registration_date,
                    'vehicle_registration_no' => $new_rc_number,
                    'previous_policy_expiry_date' => $policy_expiry, //'11-11-2021',
                    'previous_policy_type' => $previous_policy_type,
                    // 'fuel_type' => $response['vehicle_data']['fuel_type'], //'PETROL',
                    'fuel_type' => $response['fuel_type'] ?? '', //'PETROL',
                    'manufacture_year' => $manf_year,
                    'vehicle_invoice_date' => $invoiceDate,
                    "vehicle_owner_type" => "I",
                    'rto_code' => isBhSeries($rto_code) ? null : $rto_code,
                    'rto_city' => isBhSeries($rto_name) ? null : $rto_name,
                    'previous_ncb' => $previous_ncb,
                    'applicable_ncb' => $applicable_ncb,
                    'previous_insurer' => $previous_insurance,
                    'previous_insurer_code' => $inapp ? '' : $previous_insurer_code,
                    'journey_type' => $journey_type,
                    'zero_dep_in_last_policy' => 'Y',
                    'gcv_carrier_type' => in_array($product_sub_type_id, ['9', '13', '14', '15', '16']) ? 'PUBLIC' : null,
                    'is_ncb_verified' => 'N',
                    'previous_policy_type_identifier' => 'N'
                ]
            );

            $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
            $quote_log_data = isset($quote_log->quote_data) ? json_decode($quote_log->quote_data, TRUE) : [];
            $quote_data = [
                "product_sub_type_id" => $product_sub_type_id,
                "manfacture_id" => $save_mmv ? $mmv_details['manufacturer']['manf_id'] : $quote_log_data['manfacture_id'],
                "manfacture_name" => $save_mmv ? $mmv_details['manufacturer']['manf_name'] : $quote_log_data['manfacture_name'],
                "model" => $save_mmv ? $mmv_details['model']['model_id'] : $quote_log_data['model'],
                "model_name" => $save_mmv ? $mmv_details['model']['model_name'] : $quote_log_data['model_name'],
                "version_id" => $save_mmv ? $version_code : $quote_log_data['version_id'],
                "vehicle_usage" => 2,
                "policy_type" => $policy_type,
                "business_type" => $businessType,
                "version_name" => $save_mmv ? $mmv_details['version']['version_name'] : $quote_log_data['version_name'],
                "vehicle_register_date" => $registration_date,
                "previous_policy_expiry_date" => $policy_expiry,
                "previous_policy_type" => $previous_policy_type,
                "fuel_type" => $save_mmv ? $mmv_details['version']['fuel_type'] : $quote_log_data['fuel_type'],
                "manufacture_year" => $manf_year,
                "rto_code" => isBhSeries($rto_code) ? null : $rto_code,
                "vehicle_owner_type" => "I",
                "is_claim" => "N",
                "previous_ncb" => $previous_ncb,
                "applicable_ncb" => $applicable_ncb,
                "previous_policy_type_identifier" => 'N'
            ];
            $QuoteLog = [
                'user_product_journey_id' => $enquiryId,
                'quote_data' => json_encode($quote_data),
            ];

            QuoteLog::updateOrCreate([
                'user_product_journey_id' => $enquiryId
            ], $QuoteLog);

            $curlResponse['results'][0]['vehicle']['regn_dt'] = $registration_date;
            $curlResponse['results'][0]['vehicle']['vehicle_cd'] = $save_mmv ? $version_code : $quote_log_data['version_id'];
            $curlResponse['results'][0]['vehicle']['fla_maker_desc'] = $quote_data['manfacture_name'];
            $curlResponse['results'][0]['vehicle']['fla_model_desc'] = $quote_data['model_name'];
            $curlResponse['results'][0]['vehicle']['fla_variant'] = $quote_data['version_name'];
            $curlResponse['results'][0]['insurance']['insurance_upto'] = $policy_expiry;
            $name = explode(' ', $response['owner_name']);
            $address = explode(',', $response['permanent_address']);
            preg_match_all('!\d+!', end($address), $matches);
            $pincode = '';
            if (is_array($matches)) {
                $pincode = implode(',', $matches[0]);
            }
            // If pincode is in wrong/dummy then don't store that value in DB - @Amit 03-09-2022 #8325
            if (\Illuminate\Support\Str::contains($pincode, '999999')) {
                $pincode = null;
            }

            $firstName = count($name) > 2 ? implode(' ', [$name[0], $name[1] ?? '']) : $name[0];
            $lastName = end($name);
            $return_data = [
                'applicableNcb' => $applicable_ncb,
                'businessType' => $businessType,
                "product_sub_type_id" => $product_sub_type_id,
                'corpId' => '',
                'emailId' => '',
                'enquiryId' => customEncrypt($enquiryId),
                // 'firstName' =>  count($name) > 2 ? implode(' ', [$name[0], $name[1] ?? '']) : $name[0], 
                'firstName' => $firstName,
                'fuelType' => $quote_data['fuel_type'],
                // 'fullName' => $response['owner_data']['name'],
                'fullName' => $firstName . ' ' . $lastName,
                'hasExpired' => "no",
                'isClaim' => "N",
                'isNcb' => "Yes",
                "engine_no" => removeSpecialCharactersFromString($response['vehicle_engine_number']),
                "chassis_no" => $response['vehicle_chasi_number'],
                // 'lastName' => end($name),
                'lastName' => $lastName,
                'leadJourneyEnd' => true,
                'manfactureId' => $quote_data['manfacture_id'],
                'manfactureName' => $quote_data['manfacture_name'],
                'manufactureYear' => $quote_data['manufacture_year'],
                //'mobileNo' => '',
                'model' => $quote_data['model'],
                'modelName' => $quote_data['model_name'],
                'ownershipChanged' => "N",
                'policyExpiryDate' => $policy_expiry,
                'policyType' => $policy_type,
                'previousInsurer' => $previous_insurance,
                'previousInsurerCode' => ($inapp || empty($policy_expiry)) ? '' : $previous_insurer_code,
                'previousNcb' => $previous_ncb,
                'previousPolicyType' => $previous_policy_type,
                'productSubTypeId' => $product_sub_type_id,
                "manufacture_year" => $manf_year,
                'rto' => isBhSeries($rto_code) ? null : $rto_code,
                'stage' => 11,
                //'userId' => '',
                'userProductJourneyId' => $enquiryId,
                //'vehicleLpgCngKitValue' => "",
                'vehicleOwnerType' => "I",
                'vehicleRegisterAt' => isBhSeries($rto_code) ? null : $rto_code,
                'vehicleRegisterDate' => $registration_date,
                'vehicleInvoiceDate' => $invoiceDate,
                'vehicleRegistrationNo' => $new_rc_number,
                'vehicleUsage' => 2,
                'version' => $save_mmv ? $version_code : $quote_log_data['version_id'],
                'versionName' => $quote_data['version_name'],
                'address_line1' => $response['permanent_address'],
                // 'address_line1' => $address[0] ?? $response['owner_data']['permanent_address'],
                'address_line2' => '', // $address[1] ?? '', // address_line2 and address_line3 are not used in front end so complete address is not visible
                'address_line3' => '', // end($address) ?? '',
                'pincode' => ((int)$pincode > 0) ? (int)$pincode : null,
                'financer_sel' => (isset($response['financed']) ? [($response['financed'] == 'true' ? [
                    'name' => (isset($response['financer']) ? $response['financer'] : $response['norms_type']),
                    'code' => (isset($response['financer']) ? $response['financer'] : $response['norms_type'])
                ] : '')] : ""),
                'pucNo' => $response['pucc_number'] ?? null,
                'pucExpiry' => isset($response['pucc_upto']) ? date('d-m-Y', strtotime($response['pucc_upto'])) : null
            ];
            $curlResponse['additional_details'] = $return_data;
            $curlResponse['additional_details']['vahan_service_code'] = 'surepass';

            $vehicle_color = removeSpecialCharactersFromString($response['color'] ?? null, true);
            //RenewBuy doesn't want to store the vehicle color #8325
            if (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') {
                $vehicle_color = null;
            }

            $proposalData = [
                'first_name'  => $firstName,
                'last_name' => $lastName,
                'fullName' => $firstName . ' ' . $lastName,
                'applicable_ncb' => $curlResponse['additional_details']['applicableNcb'],
                'is_claim' => 'N',
                'address_line1' => $curlResponse['additional_details']['address_line1'],
                'address_line2' => $curlResponse['additional_details']['address_line2'],
                'address_line3' => $curlResponse['additional_details']['address_line3'],
                'pincode' => $curlResponse['additional_details']['pincode'],
                'rto_location' => isBhSeries($rto_code) ? null : $rto_code,
                // 'additional_details' => json_encode($return_data),
                'vehicale_registration_number' => $new_rc_number,
                'vehicle_manf_year' => $curlResponse['additional_details']['manufacture_year'],
                'previous_insurance_company' => $response['insurance_company'] ?? null,
                'prev_policy_expiry_date' => empty($policy_expiry) ? null : date('d-m-Y', strtotime($policy_expiry)),
                'engine_number' => removeSpecialCharactersFromString($response['vehicle_engine_number']),
                // 'chassis_number' => removeSpecialCharactersFromString($response['vehicle_data']['chassis_number']),
                'chassis_number' => $response['vehicle_chasi_number'],
                'previous_policy_number' => isset($response['insurance_policy_number']) ? $response['insurance_policy_number'] : '',
                'vehicle_color' => removeSpecialCharactersFromString($vehicle_color, true),
                'is_vehicle_finance' => (isset($response['financed']) ? $response['financed'] : ""),
                'name_of_financer' => (isset($response['financed']) ? ($response['financed'] == '1' ? (isset($response['financer']) ? $response['financer'] : $response['norms_type']) : '') : ""),
                'full_name_finance' => (isset($response['financed']) ? ($response['financed'] == '1' ? (isset($response['financer']) ? $response['financer'] : $response['norms_type']) : '') : ""),
                'puc_no' => $response['pucc_data']['pucc_number'] ?? null,
                'puc_expiry' => isset($response['pucc_data']['expiry_date']) ? date('d-m-Y', strtotime($response['pucc_data']['expiry_date'])) :  null,
            ];

            if (config('constants.motorConstant.AUTO_FILL_TP_DETAILS_IN_VAHAN') == 'Y') {
                $tpExpiryDate = null;

                if (!empty($response['insurance_upto'])) {
                    $tpExpiryDate = date('d-m-Y', strtotime(str_replace('/', '-', $response['insurance_upto'])));
                }

                $previousTpInsurer = $response['insurance_company'] ?? null;
                
                if (!empty($previousTpInsurer)) {
                    $fyntuneIc = DB::table('fastlane_previous_ic_mapping as m')
                    ->whereRaw('? LIKE CONCAT("%", m.identifier, "%")', $previousTpInsurer)
                    ->first();
                    if (!empty($fyntuneIc)) {
                        $previousTpInsurer = $fyntune_ic->company_name;
                    }
                }
                
                $proposalData = array_merge($proposalData, [
                    // 'tp_start_date'                         => $tpExpiryDate,
                    'tp_end_date'                           => $tpExpiryDate,
                    'tp_insurance_company'                  => $previousTpInsurer,
                    'tp_insurance_company_name'             => $previousTpInsurer,
                    'tp_insurance_number'                   => $response['insurance_policy_number'] ?? '',
                ]);
            }

            UserProposal::updateOrCreate(
                ['user_product_journey_id' => $enquiryId],
                $proposalData
            );

            return [
                'status' => true,
                'data' => $curlResponse
            ];
        } catch (\Exception $e) {
            return [
                "status" => false,
                "message" => "Something went wrong..!" . $e->getMessage() . "on Line " . $e->getLine()
            ];
        }
    }
}
