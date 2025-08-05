<?php

namespace App\Http\Controllers\VahanService;

use DateTime;
use Exception;
use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterRto;
use App\Models\UserProposal;
use App\Models\VahanService;
use Illuminate\Http\Request;
use App\Models\CvAgentMapping;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use App\Models\FastlaneRequestResponse;
use App\Models\VahanServiceCredentials;
use Illuminate\Support\Facades\Storage;
use App\Models\CorporateVehiclesQuotesRequest;

class AdrilaServiceController extends Controller
{
    const VAHAN_SERVICE_CODE = 'adrila';
    protected $credentials;

    public function __construct()
    {
        $this->setCredentials();
    }

    protected function setCredentials()
    {
        $vahanId = VahanService::where('vahan_service_name_code', self::VAHAN_SERVICE_CODE)->select('id')->get()->first();

        if (empty($vahanId)) {
            throw new Exception('Adrila Vahan Service is not configured.');
        }

        $this->credentials = VahanServiceCredentials::where('vahan_service_id', $vahanId->id)->select('value', 'key')->get()->pluck('value', 'key')->toArray();
    }

    public function getCredential($keyName)
    {
        return $this->credentials[$keyName] ?? null;
    }

    public function getVahanDetails(Request $request)
    {
        if ($request->type == 'PRO') {
            return $this->getProVahanDetails($request);
        }

        $request->validate([
            'enquiryId' => 'required',
            'registration_no' => 'required',
            'section' => 'required',
        ]);
        try {
            $service = "online";
            $enquiryId = customDecrypt($request->enquiryId);
            $rcno = explode('-', $request->registration_no);
            if ($rcno[0] == 'DL') {
                $rc_no = $rcno[0] . ((int) $rcno[1] * 1) . $rcno[2] . $rcno[3];
            } else {
                $rc_no = preg_replace('/-/', '', $request->registration_no);
            }
            $request_array = [
                'application_name' => $this->getCredential('vahan.adrila.attribute.application_name'), //'ACE',
                'rc_no' => $rc_no,
                'api_type' => 'LITE',
                'rc_source' => 1,
                'section' => $request->section,
            ];
            $startTime = new DateTime(date('Y-m-d H:i:s'));
            $insudetails = Http::withoutVerifying()->withHeaders([
                'Authorization' => $this->getCredential('vahan.adrila.attribute.authorization'), //config('ADRILA_API_KEY'),
            ])->post($this->getCredential('vahan.adrila.attribute.url'), $request_array);
            $endTime = new DateTime(date('Y-m-d H:i:s'));

            $responseTime = $startTime->diff($endTime);
            $insudetails = json_decode($insudetails, true);

            FastlaneRequestResponse::insert([
                'enquiry_id' => $enquiryId,
                'request' => $request->registration_no,
                'response' => json_encode($insudetails),
                'transaction_type' => "Adrila Lite Service",
                'endpoint_url' => $this->getCredential('vahan.adrila.attribute.url'),
                'ip_address' => $request->ip(),
                'section' => $request->section,
                'response_time' => $responseTime->format('%H:%i:%s'),
                'created_at' => now(),
                'type' => 'input',
            ]);
            if ($insudetails == null) {
                return response()->json([
                    "status" => false,
                    "message" => "Something went wrong please try again",
                ]);
            }

            if ($insudetails['status'] != true) {
                return response()->json([
                    "status" => false,
                    "message" => $insudetails['output'],
                ]);
            }
            $insudetails = $insudetails['output'];
            $response = $insudetails;
            $variantdata = $response['data']['pass_id_data'][0]['results'];
            $variantnewdata = [];
            $sectiontype = '';
            $vehicle_section = DB::table('fastlane_vehicle_description')->where('description', $response['data']['rc_vh_class_desc'])->first();
            if (empty($vehicle_section) || empty($vehicle_section->section)) {
                return response()->json([
                    "status" => false,
                    "message" => 'Vehicle description mapping not found.',
                ]);
            }
            $sectiontype = $vehicle_section->section;
            $variant_section = '';
            if ($sectiontype == 'cv') {
                $variant_section = 'PCV';
            } else if ($sectiontype == 'car') {
                $variant_section = 'CRP';
            } else if ($sectiontype == 'bike') {
                $variant_section = 'BYK';
            }

            if ($variant_section == '') {
                return response()->json([
                    "status" => false,
                    "message" => 'Variant Section not found',
                ]);
            }
            $variantnewdata = array_values(array_filter($variantdata, function ($var) use ($variant_section) {
                return (\Illuminate\Support\Str::substr($var['variant_id'], 0, 3) == $variant_section);
            }));
            // If section is cv and variantdata is empty then it is a GCV vehicle.
            if (empty($variantnewdata) && $sectiontype == 'cv') {
                $variantnewdata = array_values(array_filter($variantdata, function ($var) use ($variant_section) {
                    return (\Illuminate\Support\Str::substr($var['variant_id'], 0, 3) == 'GCV');
                }));
            }
            if (empty($variantnewdata)) {
                return response()->json([
                    "status" => false,
                    "message" => 'Vehicle type Section not found',
                ]);
            }
            $newversiondata = [];
            $max = 0;
            for ($i = 0; $i < count($variantnewdata); $i++) {
                if ($variantnewdata[$i]['confidence_score'] > $max) {
                    $max = $variantnewdata[$i]['confidence_score'];
                    $newversiondata = $variantnewdata[$i];
                }
            }

            $response['data']['variant_id'] = $newversiondata['variant_id'];
            switch (\Illuminate\Support\Str::substr($response['data']['variant_id'], 0, 3)) {
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
            $response['data']['rc_regn_dt'] = date('Y-m-d', strtotime($response['data']['rc_regn_dt']));
            $manufacture_year = date('Y-m-d', strtotime($response['data']['rc_regn_dt']));
            $manufacture_year = explode('-', $response['data']['rc_regn_dt']);
            $manufacture_year = $manufacture_year[1] . '-' . $manufacture_year[0];

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
            $product_sub_type_id = array_search($type, $producttype);

            $sub_type_data = DB::table('master_product_sub_type')
                ->where('product_sub_type_id', $product_sub_type_id)
                ->where('status', 'Active')
                ->first();

            if (empty($sub_type_data)) {
                return response()->json([
                    "data" => [
                        "status" => 101,
                        "dataSource" => $service
                    ],
                    "status" => false,
                    "showMessage" => true,
                    "message" => 'Selected product(' . strtoupper($type) . ') is Inactive',
                ]);
            }

            $name = explode(' ', $response['data']['rc_owner_name']);
            $insudetails['data']['rc_vh_class_desc'] = $sectiontype;
            $user_product_journey = [
                // 'user_fname' => $name[0],
                // 'user_lname' => end($name),
                'product_sub_type_id' => $product_sub_type_id,
                'status' => 'yes',
                'lead_stage_id' => 2,
            ];
            $user_product_journey = UserProductJourney::where('user_product_journey_id', $enquiryId)->update($user_product_journey);
            $vehicle_details = self::getAdrilaVehicleDetails($enquiryId, $request->registration_no, $type, $response, $insudetails, $journey_type = self::VAHAN_SERVICE_CODE, $pro = false);
            if ($vehicle_details['status'] == 101) {
                return response()->json([
                    "data" => [
                        "status" => 101,
                        "dataSource" => $service
                    ],
                    "status" => false,
                    "message" => $vehicle_details['message'],
                    "url" => config('constants.frontend_url'),
                ]);
            }
            $sectiontype = ($sectiontype == 'gcv') ? 'cv' : $sectiontype;
            $vehicle_details['data']['ft_product_code'] = $sectiontype;
            $vehicle_details['data']['dataSource'] = $service;

            $type = $type == 'motor' ? 'car' : $type;
            return response()->json([
                "status" => true,
                'url' => config('constants.frontend_url') . $sectiontype . "/quotes?enquiry_id=" . customEncrypt($enquiryId),
                "data" => $vehicle_details['data'],
            ]);
        } catch (\Exception$e) {
            return response()->json([
                "data" => [
                    "status" => 101,
                    "dataSource" => $service
                ],
                "status" => false,
                "message" => "error " . $e->getMessage(),
                "url" => config('constants.frontend_url'),
            ]);
        }
    }

    public function validateVehicleService(Request $request)
    {
        $request->validate([
            'enquiryId' => 'required',
            'registration_no' => 'required',
            'section' => 'required',
        ]);
        try {
            $enquiryId = customDecrypt($request->enquiryId);
            $isResponseValid = false;

            $existing_record = DB::table('proposal_vehicle_validation_logs')
                ->select('response')
                ->where('vehicle_reg_no', $request->registration_no)
                ->where('service_type', self::VAHAN_SERVICE_CODE)
                ->orderBy('id', 'DESC')->first();

            if (empty($existing_record)) {
                $vehicle_lite_data = DB::table('fastlane_request_response')
                    ->where(['transaction_type' => 'Adrila Lite Service', 'request' => $request->registration_no])
                    ->select('response')
                    ->orderBy('id', 'desc')
                    ->limit(1)
                    ->first();
                if (!empty($vehicle_lite_data)) {
                    $response = json_decode($vehicle_lite_data->response, true);
                    if (isset($pass_id_data['status']) && $pass_id_data['status'] == true) {
                        $isResponseValid = (isset($response['output']['data']['rc_vh_class_desc']) && !empty($response['output']['data']['rc_vh_class_desc']));
                    }
                }
            } else {
                $response = json_decode($existing_record->response, true);
                $isResponseValid = (isset($response['output']['data']['rc_vh_class_desc']) && !empty($response['output']['data']['rc_vh_class_desc']));
            }

            $service = 'offline';

            // If response is not valid or record doesn't in DB, then hit API
            if (!$isResponseValid) {
                $service = 'online';
                $rcno = explode('-', $request->registration_no);
                if ($rcno[0] == 'DL') {
                    $rc_no = $rcno[0] . ((int) $rcno[1] * 1) . $rcno[2] . $rcno[3];
                } else {
                    $rc_no = preg_replace('/-/', '', $request->registration_no);
                }
                $request_array = [
                    'application_name' => $this->getCredential('vahan.adrila.attribute.application_name'), //'ACE',
                    'rc_no' => $rc_no,
                    'api_type' => 'LITE',
                    'rc_source' => 1,
                    'section' => $request->section,
                ];
                $startTime = new DateTime(date('Y-m-d H:i:s'));
                $insudetails = Http::withoutVerifying()->withHeaders([
                    'Authorization' => $this->getCredential('vahan.adrila.attribute.authorization'), //config('ADRILA_API_KEY'),
                ])->post($this->getCredential('vahan.adrila.attribute.url'), $request_array);
                $endTime = new DateTime(date('Y-m-d H:i:s'));

                $responseTime = $startTime->diff($endTime);
                $insudetails = json_decode($insudetails, true);

                FastlaneRequestResponse::insert([
                    'enquiry_id' => $enquiryId,
                    'request' => $request->registration_no,
                    'response' => json_encode($insudetails),
                    'transaction_type' => "Adrila Lite Service",
                    'endpoint_url' => $this->getCredential('vahan.adrila.attribute.url'),
                    'ip_address' => $request->ip(),
                    'section' => $request->section,
                    'response_time' => $responseTime->format('%H:%i:%s'),
                    'created_at' => now(),
                    'type' => 'proposal',
                ]);
                if ($insudetails == null) {
                    return response()->json([
                        "status" => false,
                        "message" => "Something went wrong please try again",
                        'data' => [
                            'status' => 101,
                            'dataSource' => $service
                        ],
                    ]);
                }
                if ($insudetails['status'] != true) {
                    return response()->json([
                        "status" => false,
                        "message" => $insudetails['output'],
                        "data" => [
                            "status" => 101,
                            'dataSource' => $service
                        ],
                    ]);
                }
                DB::table('proposal_vehicle_validation_logs')->insert([
                    'vehicle_reg_no' => $request->registration_no,
                    'response' => json_encode($insudetails),
                    'service_type' => self::VAHAN_SERVICE_CODE,
                    'endpoint_url' => $this->getCredential('vahan.adrila.attribute.url'),
                    'created_at' => now(),
                ]);
                $response = $insudetails;
            }

            $response = $response['output'];
            $sectiontype = '';
            $category_description = $response['data']['rc_vh_class_desc'];
            $vehicle_section = DB::table('fastlane_vehicle_description')->where('description', $category_description)->first();
            if (!empty($vehicle_section)) {
                $sectiontype = $vehicle_section->section;
            } else if (self::findMyWord($category_description, 'LMV') || self::findMyWord($category_description, 'Motor Car')) {
                $sectiontype = 'car';
            } else if (self::findMyWord($category_description, 'LPV') || self::findMyWord($category_description, 'Motor Cab')) {
                $sectiontype = 'cv';
            } else if (self::findMyWord($category_description, 'LGV') || self::findMyWord($category_description, 'Goods Carrier')) {
                $sectiontype = 'cv';
            } else if (self::findMyWord($category_description, 'Scooter')) {
                $sectiontype = 'bike';
            }

            $vehicle_details['dataSource'] = $service;

            if (strtolower($sectiontype) == strtolower($request->section)) {
                $version_id = $response['data']['variant_id'];
                if (!empty($version_id)) {
                    $ValidateQuoteAndVahanMmv = \App\Helpers\VahanHelper::validateQuouteAndVahanMMv($request, $version_id, $enquiryId);
                    if (!$ValidateQuoteAndVahanMmv['status']) {
                        return response()->json($ValidateQuoteAndVahanMmv);
                    }
                }
                $vehicle_details['status'] = 100;
            } else {
                $vehicle_details['status'] = 100;
                if (empty($sectiontype)) {
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
                    // If FT code is empty we need to pass status code as 101 only in data array tag - @Amit:18-10-2022
                    $vehicle_details['status'] = 101;
                    $vehicle_details['data']['message'] = 'Unable to match vehicle description.';
                }
                CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->update([
                    'vehicle_registration_no' => $request->registration_no,
                ]);
            }
            $vehicle_details['ft_product_code'] = $sectiontype;
            $vehicle_details['redirectionUrl'] = config('constants.motorConstant.' . strtoupper($sectiontype) . '_FRONTEND_URL');
            return response()->json([
                "status" => true,
                'url' => config('constants.frontend_url') . $sectiontype . "/quotes?enquiry_id=" . customEncrypt($enquiryId),
                "data" => $vehicle_details,
            ]);
        } catch (\Exception$e) {
            return response()->json([
                "data" => [
                    "status" => 101,
                ],
                "status" => false,
                "message" => "error " . $e->getMessage(),
                "url" => config('constants.frontend_url'),
            ]);
        }
    }

    public function getProVahanDetails(Request $request)
    {
        $request->validate([
            'enquiryId' => 'required',
            'registration_no' => 'required',
            'section' => 'required',
        ]);
        try {
            $rcno = explode('-', $request->registration_no);
            if ($rcno[0] == 'DL') {
                $rc_no = $rcno[0] . ((int) $rcno[1] * 1) . $rcno[2] . $rcno[3];
            } else {
                $rc_no = preg_replace('/-/', '', $request->registration_no);
            }
            $request_array = [
                'application_name' => $this->getCredential('vahan.adrila.attribute.application_name'), //'ACE',
                'rc_no' => $rc_no,
                'api_type' => 'PROPLUS',
                'rc_source' => 1,
                'section' => $request->section,
            ];
            $startTime = new DateTime(date('Y-m-d H:i:s'));
            $insudetails = Http::withHeaders([
                'Authorization' => $this->getCredential('vahan.adrila.attribute.authorization'),
            ])->post($this->getCredential('vahan.adrila.attribute.url'), $request_array);
            $endTime = new DateTime(date('Y-m-d H:i:s'));

            $responseTime = $startTime->diff($endTime);
            $insudetails = json_decode($insudetails, true);
            $enquiryId = customDecrypt($request->enquiryId);
            FastlaneRequestResponse::insert([
                'enquiry_id' => $enquiryId,
                'request' => $request->registration_no,
                'response' => json_encode($insudetails),
                'transaction_type' => "Adrila Pro Service",
                'endpoint_url' => $this->getCredential('vahan.adrila.attribute.url'),
                'ip_address' => $request->ip(),
                'section' => $request->section,
                'response_time' => $responseTime->format('%H:%i:%s'),
                'created_at' => now(),
                'type' => 'proposal',
            ]);
            if ($insudetails == null) {
                return response()->json([
                    "data" => [
                        "status" => 101,
                    ],
                    "status" => false,
                    "message" => "Something went wrong please try again",
                ]);
            }
            if ($insudetails['status'] != true) {
                return response()->json([
                    "data" => [
                        "status" => 101,
                    ],
                    "status" => false,
                    "message" => $insudetails['output'],
                ]);
            }

            $insudetails = $insudetails['output'];
            $response = $insudetails;
            $vehicle_lite_data = DB::table('fastlane_request_response')
                ->where(['transaction_type' => 'Adrila Lite Service', 'request' => $request->registration_no])
                ->orderBy('id', 'desc')
                ->limit(1)
                ->first();
            if (empty($vehicle_lite_data)) {
                return response()->json([
                    "data" => [
                        "status" => 101,
                    ],
                    "status" => false,
                    "message" => 'Version Data not found in lite',
                ]);
            }
            $pass_id_data = json_decode($vehicle_lite_data->response, true);

            if (isset($pass_id_data['status']) && $pass_id_data['status'] == false) {
                return response()->json([
                    "data" => [
                        "status" => 101,
                    ],
                    "status" => false,
                    "message" => $pass_id_data['output'],
                ]);
            }

            $variantdata = $pass_id_data['output']['data']['pass_id_data'][0]['results'];
            $variantnewdata = [];
            $sectiontype = 'car';
            if (self::findMyWord($response['data']['rc_vh_class_desc'], 'LMV') || self::findMyWord($response['data']['rc_vh_class_desc'], 'Motor Car')) {
                $variantnewdata = array_values(array_filter($variantdata, function ($var) {
                    return (\Illuminate\Support\Str::substr($var['variant_id'], 0, 3) == 'CRP');
                }));
            } else if (self::findMyWord($response['data']['rc_vh_class_desc'], 'LPV') || self::findMyWord($response['data']['rc_vh_class_desc'], 'Motor Cab')) {
                $sectiontype = 'cv';
                $variantnewdata = array_values(array_filter($variantdata, function ($var) {
                    return (\Illuminate\Support\Str::substr($var['variant_id'], 0, 3) == 'PCV');
                }));
            } else if (self::findMyWord($response['data']['rc_vh_class_desc'], 'LGV') || self::findMyWord($response['data']['rc_vh_class_desc'], 'Goods Carrier')) {
                $sectiontype = 'cv';
                $variantnewdata = array_values(array_filter($variantdata, function ($var) {
                    return (\Illuminate\Support\Str::substr($var['variant_id'], 0, 3) == 'GCV');
                }));
            } else if (self::findMyWord($response['data']['rc_vh_class_desc'], 'Scooter')) {
                $sectiontype = 'bike';
                $variantnewdata = array_values(array_filter($variantdata, function ($var) {
                    return (\Illuminate\Support\Str::substr($var['variant_id'], 0, 3) == 'BYK');
                }));
            }
            $newversiondata = [];
            $max = 0;
            for ($i = 0; $i < count($variantnewdata); $i++) {
                if ($variantnewdata[$i]['confidence_score'] > $max) {
                    $max = $variantnewdata[$i]['confidence_score'];
                    $newversiondata = $variantnewdata[$i];
                }
            }

            $response['data']['variant_id'] = $newversiondata['variant_id'];

            switch (\Illuminate\Support\Str::substr($response['data']['variant_id'], 0, 3)) {
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
            $response['data']['rc_regn_dt'] = date('Y-m-d', strtotime($response['data']['rc_regn_dt']));
            $manufacture_year = date('Y-m-d', strtotime($response['data']['rc_regn_dt']));
            // $insudetails['data']['rc_insurance_upto'] = date('Y-m-d', strtotime($insudetails['data']['rc_insurance_upto']));
            $manufacture_year = explode('-', $response['data']['rc_regn_dt']);
            $manufacture_year = $manufacture_year[1] . '-' . $manufacture_year[0];

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
            $product_sub_type_id = array_search($type, $producttype);

            $sub_type_data = DB::table('master_product_sub_type')
                ->where('product_sub_type_id', $product_sub_type_id)
                ->where('status', 'Active')
                ->first();

            if (empty($sub_type_data)) {
                return response()->json([
                    "data" => [
                        "status" => 101,
                    ],
                    "status" => false,
                    "message" => 'Vehicle type Section not found',
                ]);
            }

            $name = explode(' ', $response['data']['rc_owner_name']);
            $user_product_journey = [
                // 'user_fname' => $name[0],
                // 'user_lname' => end($name),
                'product_sub_type_id' => $product_sub_type_id,
                'status' => 'yes',
                'lead_stage_id' => 2,
            ];
            $user_product_journey = UserProductJourney::where('user_product_journey_id', $enquiryId)->update($user_product_journey);
            $vehicle_details = self::getAdrilaVehicleDetails($enquiryId, $request->registration_no, $type, $response, $insudetails, $journey_type = self::VAHAN_SERVICE_CODE, $pro = true);
            if ($vehicle_details['status'] == 101) {
                return response()->json([
                    "data" => [
                        "status" => 101,
                    ],
                    "status" => false,
                    "message" => $vehicle_details['message'],
                ]);
            }

            return response()->json([
                "status" => true,
                "data" => $vehicle_details['data'],

            ]);

        } catch (\Exception$e) {
            return response()->json([
                "data" => [
                    "status" => 101,
                ],
                "status" => false,
                "message" => "error " . $e->getMessage(),
                "url" => config('constants.frontend_url'),
            ]);
        }

    }

    public static function findMyWord($s, $w)
    {
        if (strpos($s, $w) !== false) {
            return true;
        } else {
            return false;
        }
    }

    public static function countStringInArray($arr)
    {
        $len = 0;

        foreach ($arr as $row) {
            $len += strlen($row);
        }

        if ($len > 33) {
            array_pop($arr);
            self::countStringInArray($arr);
        }

        return $arr;

    }
    public static function getAdrilaVehicleDetails($enquiryId, $new_rc_number, $type, $response, $insudetails, $journey_type, $pro = false)
    {
        $version_code = '';

        if (strtotime($insudetails['data']['rc_insurance_upto']) === false || Carbon::hasRelativeKeywords($insudetails['data']['rc_insurance_upto'])) {
            $insudetails['data']['rc_insurance_upto'] = '';
        }

        $insudetails['data']['rc_insurance_upto'] = (!empty($insudetails['data']['rc_insurance_upto']) ? date('Y-m-d', strtotime($insudetails['data']['rc_insurance_upto'])) : "");
        $version_id = $response['data']['variant_id'];
        $env = config('app.env');
        if ($env == 'local') {
            $env_folder = 'uat';
        } else if ($env == 'test' || $env == 'live') {
            $env_folder = 'production';
        }
        $product = $type;
        $product = $product == 'car' ? 'motor' : $product;
        // $path = 'storage/mmv_masters/' . $env_folder . '/';
        $path = 'mmv_masters/' . $env_folder . '/';
        $file_name = $path . $product . '_model_version.json';
        // $data = json_decode(file_get_contents($file_name), true);
        $data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($file_name), true);
        if ($data) {
            foreach ($data as $value) {
                if ($value['version_id'] == $version_id) {
                    $version_code = $value['version_id'];
                }
            }
        }
        if (empty($version_code)) {
            return [
                'status' => 101,
                'message' => 'Version ID not found in file for ' . $version_id,
            ];
        }
        $fast_lane_product_code = $insudetails['data']['rc_vh_class_desc'];
        $ft_product_code = '';
        if (strpos($fast_lane_product_code, 'LMV') !== false || strpos($fast_lane_product_code, 'Motor Car') !== false) {
            $ft_product_code = 'car';
        } else if (in_array($fast_lane_product_code, ['gcv', 'pcv'])) {
            $ft_product_code = 'cv';
        } else {
            $ft_product_code = 'bike';
        }
        $registration_date = date('d-m-Y', strtotime(str_replace('/', '-', $response['data']['rc_regn_dt'])));
        if (isset($insudetails['data']['rc_insurance_upto']) && !empty($insudetails['data']['rc_insurance_upto'])) {
            $policy_expiry = date('d-m-Y', strtotime(str_replace('/', '-', $insudetails['data']['rc_insurance_upto'])));
            $date1 = new \DateTime($registration_date);
            $date2 = new \DateTime($policy_expiry);
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
        $manf_year = explode('-', $response['data']['rc_regn_dt']);
        $manf_year = date('m', strtotime($registration_date)) . '-' . $manf_year[0];
        $invoiceDate = $registration_date;
        $reg_no = explode('-', $new_rc_number);
        $rto_code = implode('-', [$reg_no[0], $reg_no[1]]);
        $rto_name = MasterRto::where('rto_code', $rto_code)->pluck('rto_name')->first();
//        if (!$rto_name) {
//            return [
//                'status' => 101,
//                'message' => 'RTO details not found for rto code ' . $rto_code,
//            ];
//        }
        // Fetch company alias from
        $previous_insurer = '';
        $previous_insurer_code = '';

        $expiry_date = \Carbon\Carbon::parse($insudetails['data']['rc_insurance_upto']);
        $today_date = now()->subDay(1);
        if ($expiry_date < $today_date) {
            $businessType = 'breakin';
            $diff = $expiry_date->diffInDays(now());
            if ($diff > 90) {
                $previous_ncb = 0;
                $applicable_ncb = 0;
            }
        } else {
            $businessType = 'rollover';
        }

        if ($insudetails['data']['rc_insurance_upto'] == '') {
            $businessType = 'breakin';
            $previous_ncb = 0;
            $applicable_ncb = 0;
        }

        $od_compare_date = now();
        if ($ft_product_code == 'car') {
            $od_compare_date = now()->subYear(3)->subDay(45);
        } else if ($ft_product_code == 'bike') {
            $od_compare_date = Carbon::parse('01-09-2018')->subDay(1);
        }

        if (Carbon::parse($registration_date) > $od_compare_date && in_array($ft_product_code, ['car', 'bike'])) {
            $policy_type = 'own_damage';
        } else {
            $policy_type = 'comprehensive';
        }
        if (strpos($fast_lane_product_code, 'cv') !== false || strpos($fast_lane_product_code, 'LPV') !== false || strpos($fast_lane_product_code, 'LGV') !== false) {
            $policy_type = 'comprehensive';
        }
        $currentdate = date('d-m-Y');
        // Declare two dates
        $start_date = strtotime($currentdate);
        $end_date = strtotime($policy_expiry);
        $diff_in_days = ($end_date - $start_date) / 60 / 60 / 24;
        if ($diff_in_days > 45) {
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
        $curlResponse = [];
        $product_sub_type_id = array_search($product, $producttype);
        if (!$pro) {
            CorporateVehiclesQuotesRequest::updateOrCreate(
                [
                    'user_product_journey_id' => $enquiryId,
                ],
                [
                    'version_id' => $version_code,
                    'user_product_journey_id' => $enquiryId,
                    'policy_type' => $policy_type,
                    'product_id' => $product_sub_type_id,
                    'business_type' => $businessType, #'rollover',
                    'vehicle_register_date' => $registration_date,
                    'vehicle_registration_no' => $new_rc_number,
                    'previous_policy_expiry_date' => $policy_expiry, //'11-11-2021',
                    'previous_policy_type' => 'Comprehensive',
                    'fuel_type' => $response['data']['rc_fuel_desc'], //'PETROL',
                    'manufacture_year' => $manf_year,
                    'vehicle_invoice_date' => $invoiceDate,
                    'rto_code' => isBhSeries($rto_code) ? null : $rto_code,
                    'rto_city' => isBhSeries($rto_name) ? null : $rto_name,
                    'previous_ncb' => $previous_ncb,
                    'applicable_ncb' => $applicable_ncb,
                    'previous_insurer' => ($pro ? $previous_insurer : ''),
                    'previous_insurer_code' => $previous_insurer_code,
                    'journey_type' => $journey_type,
                    'zero_dep_in_last_policy' => 'Y',
                    'is_ncb_verified' => $applicable_ncb > 0 ? 'Y' : 'N'
                    // "vehicle_owner_type"=>(isset($insudetails['data']['rc_f_name']) && $insudetails['data']['rc_f_name']=='NA' ? "C" : "I")
                ]
            );
        } else {
            $ncb_previous_ic_popup_disabled = config('NCB_PREVIOUS_IC_POPUP_DISABLED') == 'Y';
            $ncb_previous_ic_popup_seller_types = [];

            $cv_agent_mappings = CvAgentMapping::where('user_product_journey_id', $enquiryId)->first();

            if (config('NCB_PREVIOUS_IC_POPUP_SELLER_TYPES')) {
                $ncb_previous_ic_popup_seller_types = explode(',', config('NCB_PREVIOUS_IC_POPUP_SELLER_TYPES'));

                if ( ! empty($cv_agent_mappings->user_id)) {
                    array_push($ncb_previous_ic_popup_seller_types, NULL);
                }
            }

            $adrila_ic_name = $insudetails['data']['rc_insurance_comp'];
            $fyntune_ic = DB::table('fastlane_previous_ic_mapping as m')->whereRaw('? LIKE CONCAT("%", m.identifier, "%")', $adrila_ic_name)->first();
            if (( ! $ncb_previous_ic_popup_disabled || ($ncb_previous_ic_popup_disabled && ! in_array($cv_agent_mappings?->seller_type, $ncb_previous_ic_popup_seller_types))) && $fyntune_ic) {
                $previous_insurer = $fyntune_ic->company_name;
                $previous_insurer_code = $fyntune_ic->company_alias;
            }

            if (!empty($previous_insurer) && !empty($previous_insurer_code)) {
                CorporateVehiclesQuotesRequest::updateOrCreate(
                    [
                        'user_product_journey_id' => $enquiryId,
                    ],
                    [
                        'previous_insurer' => $previous_insurer,
                        'previous_insurer_code' => $previous_insurer_code,
                    ]
                );
            }
        }
        $mmv_details = get_fyntune_mmv_details($product_sub_type_id, $version_code);
        if (!$mmv_details['status']) {
            return [
                'status' => 101,
                'message' => 'MMV details not found. - ' . $version_code,
            ];
        }
        $mmv_details = $mmv_details['data'];

        $quote_data = [
            "product_sub_type_id" => $product_sub_type_id,
            "manfacture_id" => $mmv_details['manufacturer']['manf_id'],
            "manfacture_name" => $mmv_details['manufacturer']['manf_name'],
            "model" => $mmv_details['model']['model_id'],
            "model_name" => $mmv_details['model']['model_name'],
            "version_id" => $version_code,
            "vehicle_usage" => 2,
            "policy_type" => $policy_type,
            "business_type" => $businessType,
            "version_name" => $mmv_details['version']['version_name'],
            "vehicle_register_date" => $registration_date,
            "previous_policy_expiry_date" => $policy_expiry,
            "previous_policy_type" => "Comprehensive",
            "fuel_type" => $mmv_details['version']['fuel_type'],
            "manufacture_year" => $manf_year,
            "rto_code" => isBhSeries($rto_code) ? null : $rto_code,
            "vehicle_owner_type" => (isset($insudetails['data']['rc_f_name']) && $insudetails['data']['rc_f_name'] == 'NA' ? "C" : "I"),
            "is_claim" => "N",
            "previous_ncb" => $previous_ncb,
            "applicable_ncb" => $applicable_ncb,
        ];
        if (!$pro) {
            CorporateVehiclesQuotesRequest::updateOrCreate(
                [
                    'user_product_journey_id' => $enquiryId,
                ],
                [
                    'fuel_type' => $mmv_details['version']['fuel_type']
                ]
            );
        }
        $QuoteLog = [
            'user_product_journey_id' => $enquiryId,
            'quote_data' => json_encode($quote_data),
        ];
        if (!$pro) {
            QuoteLog::updateOrCreate([
                'user_product_journey_id' => $enquiryId,
            ], $QuoteLog);
        }
        $curlResponse['results'][0]['vehicle']['regn_dt'] = $registration_date;
        $curlResponse['results'][0]['vehicle']['vehicle_cd'] = $version_code;
        $curlResponse['results'][0]['vehicle']['fla_maker_desc'] = $quote_data['manfacture_name'];
        $curlResponse['results'][0]['vehicle']['fla_model_desc'] = $quote_data['model_name'];
        $curlResponse['results'][0]['vehicle']['fla_variant'] = $quote_data['version_name'];
        $curlResponse['results'][0]['insurance']['insurance_upto'] = $policy_expiry;
        $name = explode(' ', $insudetails['data']['rc_owner_name']);
        $compname = '';
        $firstname = '';
        $lastname = '';

        $CorporateVehiclesQuotesRequest = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)
            ->get()
            ->first();

        if ($CorporateVehiclesQuotesRequest->vehicle_owner_type == 'I') {
            // $firstname=trim(count($name)==1? $name[0] : str_replace(end($name),"",$insudetails['data']['rc_owner_name']));
            // $lastname=trim(count($name)==1? "" : end($name));
            $lastname = ((count($name) - 1 > 0) ? $name[count($name) - 1] : "");
            if (isset($name[count($name) - 1]) && (count($name) - 1 > 0)) {
                unset($name[count($name) - 1]);
            }
            $firstname = implode(' ', $name);

        } else {
            $compname = array_values(array_filter(explode(' ', $insudetails['data']['rc_owner_name'])));

            $compname = implode(' ', self::countStringInArray($compname));

            $firstname = trim($insudetails['data']['rc_owner_name']);
            $lastname = '';
        }

        $previous_policy_type = 'Not sure';

        if (!empty($insudetails['data']['rc_insurance_upto']) && strlen(str_replace('-', '', $insudetails['data']['rc_insurance_upto']))) {
            $previous_policy_type = 'Comprehensive';
        }

        if ($previous_policy_type == 'Not sure') {
            $policy_expiry = 'New';
            if (!$pro) {
                CorporateVehiclesQuotesRequest::updateOrCreate(
                    [
                        'user_product_journey_id' => $enquiryId,
                    ],
                    [
                        'previous_policy_type' => $previous_policy_type,
                        'previous_policy_expiry_date' => 'New',
                    ]
                );
                $quote_data['previous_policy_type'] = $previous_policy_type;
                $QuoteLog = [
                    'user_product_journey_id' => $enquiryId,
                    'quote_data' => json_encode($quote_data),
                ];
                QuoteLog::updateOrCreate([
                    'user_product_journey_id' => $enquiryId,
                ], $QuoteLog);

            }
        }

        $address = '';
        $pincode = 0;
        $return_data = [
            'applicableNcb' => $applicable_ncb,
            'businessType' => $businessType,
            "product_sub_type_id" => $product_sub_type_id,
            'corpId' => '',
            'emailId' => '',
            'enquiryId' => customEncrypt($enquiryId),
            'firstName' => $firstname,
            'fuelType' => $quote_data['fuel_type'],
            'fullName' => $insudetails['data']['rc_owner_name'],
            'hasExpired' => "no",
            'isClaim' => "N",
            'isNcb' => "Yes",
            'lastName' => $lastname,
            'leadJourneyEnd' => true,
            'manfactureId' => $quote_data['manfacture_id'],
            'manfactureName' => $quote_data['manfacture_name'],
            'manufactureYear' => $quote_data['manufacture_year'],
            'model' => $quote_data['model'],
            'modelName' => $quote_data['model_name'],
            'ownershipChanged' => "N",
            'policyExpiryDate' => $policy_expiry,
            'policyType' => $policy_type,
            'previousInsurer' => $previous_insurer,
            'previousInsurerCode' => $previous_insurer_code,
            'previousNcb' => $previous_ncb,
            'previousPolicyType' => $previous_policy_type,
            'productSubTypeId' => $product_sub_type_id,
            "manufacture_year" => $manf_year,
            'rto' => isBhSeries($rto_code) ? null : $rto_code,
            'stage' => 11,
            'userProductJourneyId' => $enquiryId,
            'vehicleOwnerType' => (isset($insudetails['data']['rc_f_name']) && $insudetails['data']['rc_f_name'] == 'NA' ? "C" : "I"),
            'vehicleRegisterAt' => isBhSeries($rto_code) ? null : $rto_code,
            'vehicleRegisterDate' => $registration_date,
            'vehicleRegistrationNo' => $new_rc_number,
            'vehicleUsage' => 2,
            'version' => $version_code,
            'versionName' => $quote_data['version_name'],
            'address_line1' => '',
            'address_line2' => '',
            'address_line3' => '',
            'engine_number' => removeSpecialCharactersFromString($insudetails['data']['rc_eng_no']),
            // 'chassis_number' => removeSpecialCharactersFromString($insudetails['data']['rc_chasi_no']),
            'chassis_number' => $insudetails['data']['rc_chasi_no'],
            'pincode' => $pincode,
            // 'financer_sel' => [($insudetails['data']['financier_code_master'] != '0' ? ['name' => (isset($insudetails['data']['financier_name_master']) ? $insudetails['data']['financier_name_master'] : $response['norms_type']), 'code' => (isset($insudetails['data']['financier_name_master']) ? $insudetails['data']['financier_name_master'] : $response['norms_type'])] : '')]
        ];
        if (isset($response['data']['rc_present_address']) && $response['data']['rc_present_address'] != null) {
            $address = array_values(array_filter(explode(' ', $response['data']['rc_present_address'])));
            $length = floor(count($address) / 3);
            array_pop($address);
            $return_data['address_line1'] = implode(' ', array_slice($address, 0, $length));
            $return_data['address_line2'] = implode(' ', array_slice($address, $length, $length));
            $return_data['address_line3'] = implode(' ', array_slice($address, $length * 2, $length));
            $return_data['vehicle_color'] = $insudetails['data']['rc_color'];
            $return_data['previous_policy_number'] = $insudetails['data']['rc_insurance_policy_no'];
            // print_r([$return_data['address_line1'],$return_data['address_line2'],$return_data['address_line3']]);die;
            if (!empty($response['data']['pin_code'])) {
                $pincode = $response['data']['pin_code'];
            }
            $return_data['pincode'] = $pincode;
        }
        $curlResponse['additional_details'] = $return_data;
        $curlResponse['additional_details']['vahan_service_code'] = self::VAHAN_SERVICE_CODE;
        $curlResponse["status"] = 100;
        if ($pro) {

            $proposalData = [
                'first_name' => $firstname,
                'last_name' => $lastname,
                'fullName' => $insudetails['data']['rc_owner_name'],
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
                // 'previous_insurance_company' => $insudetails['data']['rc_insurance_comp'],
                // 'insurance_company_name' => $insudetails['data']['rc_insurance_comp'],
                'insurance_company_name' => $previous_insurer,
                'previous_insurance_company' => $previous_insurer_code,
                'prev_policy_expiry_date' => date('d-m-Y', strtotime($insudetails['data']['rc_insurance_upto'])),
                'engine_number' => removeSpecialCharactersFromString($insudetails['data']['rc_eng_no']),
                // 'chassis_number' => removeSpecialCharactersFromString($insudetails['data']['rc_chasi_no']),
                'chassis_number' => $insudetails['data']['rc_chasi_no'],
                'previous_policy_number' => isset($insudetails['data']['rc_insurance_policy_no']) ? $insudetails['data']['rc_insurance_policy_no'] : '',
                'vehicle_color' => $insudetails['data']['rc_color'],
                'is_vehicle_finance' => $insudetails['data']['financier_code_master'],
                'owner_type' => (isset($insudetails['data']['rc_f_name']) && $insudetails['data']['rc_f_name'] == 'NA' ? "C" : "I"),
                // 'name_of_financer' => ($response['financed'] == '1' ? (isset($response['financier']) ? $response['financier'] : $response['norms_type']) : ''),
            ];

            if (config('constants.motorConstant.AUTO_FILL_TP_DETAILS_IN_VAHAN') == 'Y') {
                $tpExpiryDate = null;

                if (!empty($insudetails['data']['rc_insurance_upto'])) {
                    $tpExpiryDate = date('d-m-Y', strtotime($insudetails['data']['rc_insurance_upto']));
                }

                $previousTpInsurer = $insudetails['data']['rc_insurance_comp'] ?? null;
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
                    'tp_insurance_number'                   => $insudetails['data']['rc_insurance_policy_no'] ?? null,
                ]);
            }

            UserProposal::updateOrCreate(
                ['user_product_journey_id' => $enquiryId],
                $proposalData
            );
            $user_product_journey = [
                'user_fname' => $firstname,
                'user_lname' => $lastname,
            ];
            UserProductJourney::where('user_product_journey_id', $enquiryId)->update($user_product_journey);

        }

        return [
            'status' => 100,
            'data' => $curlResponse,
        ];
    }

}
