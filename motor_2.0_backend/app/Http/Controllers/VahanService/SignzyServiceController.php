<?php

namespace App\Http\Controllers\VahanService;

use App\Interfaces\VahanServiceInterface;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\CvAgentMapping;
use App\Models\FastlaneCvVehicleDescription;
use App\Models\FastlanePreviousIcMapping;
use App\Models\FastlaneRequestResponse;
use App\Models\FastlaneVehDescripModel;
use App\Models\MasterRto;
use App\Models\ProposalVehicleValidationLogs;
use App\Models\QuoteLog;
use App\Models\UserProductJourney;
use App\Models\UserProposal;
use App\Models\VahanService;
use App\Models\VahanServiceCredentials;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToArray;
use App\Models\ProposalExtraFields;

class SignzyServiceController extends VahanServiceController implements VahanServiceInterface
{
    const VAHAN_SERVICE_CODE = 'signzy';
    static $blockConstantString = 'Record not found. Block journey.';
    static $blockStatusCode = '102';
    protected $credentials;

    public function __construct()
    {
        $this->setCredentials();
    }

    protected function setCredentials()
    {
        $vahanId = VahanService::where('vahan_service_name_code', 'signzy')->select('id')->get()->first();

        if (empty($vahanId)) {
            throw new \Exception('Signzy Vahan Service is not configured.');
        }
        //Add data in database is pending
        $this->credentials = VahanServiceCredentials::where('vahan_service_id', $vahanId->id)->select('value', 'key')->get()->pluck('value', 'key')->toArray();
    }

    public function getCredential($keyName)
    {
        return $this->credentials[$keyName] ?? null;
    }

    public function getVahanDetails(Request $request)
    {
        $validator = validator::make($request->all(), [
            'enquiryId' => 'required',
            'registration_no' => 'required',
            'productSubType' => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ]);
        }

        $regi_no = explode('-', $request->registration_no);
        if ($regi_no['0'] == 'DL') {
            $regi_no['1'] = $regi_no['1'] * 1;
        }
        $enquiryId = customDecrypt($request->enquiryId);

        $save_mmv = true;
        if (isset($request->vehicleValidation) && $request->vehicleValidation == 'Y' && config('constants.IS_OLA_BROKER') == 'Y') {
            $save_mmv = false;
        }

        $registratin_no = implode('', $regi_no);
        $startTime = new DateTime(date('Y-m-d H:i:s'));

        $isTenMonthsLogic = config('constants.isTenMonthsLogicEnabled') == 'Y';
        $query = DB::table('registration_details')->where('vehicle_reg_no', $request->registration_no);
        $query->where(function ($subquery) use ($isTenMonthsLogic) {
            $subquery->where('expiry_date', '>=', now()->format('Y-m-d'));
            if ($isTenMonthsLogic) {
                $subquery->orWhere('created_at', '<=', now()->subMonth(10)->format('Y-m-d 00:00:00'));
            }
        });
        $response = $query->latest()->first();

        $mappingId = $this->getMappingId($request, $enquiryId);
        if (empty($mappingId)) {
            return response()->json([
                'status' => false,
                'msg' => 'Mapping id is not configured for ' . $request->section,
                "data" => [
                    "status" => 101,
                    "vt" => $request->vt,
                ],
            ]);
        }

        $array_type = [
            'PCV' => 'cv',
            'GCV' => 'cv',
            'CRP' => 'car',
            'BYK' => 'bike',
        ];

        $vehical_url = $this->getCredential('vahan.signzy.attribute.registration.detailed.url');
       
        $vehical_body = [
            "task" => "detailedSearch",
            "essentials" => [
                "vehicleNumber" => $registratin_no,
                // FYNSZY
                "signzyID" => $mappingId,
            ],
        ];
        if (empty($response)) {

            $vehical_headers = [
                'Content-Type' => $this->getCredential('vahan.signzy.attribute.content_type'),
                'Authorization' => $this->getToken(),
            ];

            $service = 'online';
            $url = $vehical_url;
            $vehical_data = httpRequestNormal($vehical_url, "POST", $vehical_body, [], $vehical_headers, [], false);
            // $new_response = $vehical_data['response'];
            $response = $vehical_data['response'];
            $versionArray = $vehical_data['response']['result']['mappings']['variantIds'] ?? [];

            $version_id = '';
            $max_score = 0.0;

            if (isset($versionArray[0])) {
                foreach ($versionArray as $version) {
                    if ($version['score'] > $max_score) {
                        $max_score = $version['score'];
                        $version_id = isset($version['variantId']) ? strtoupper($version['variantId']) : '';
                    }
                }
            }
        } else {
            $service = 'offline';
            $response = json_decode($response->vehicle_details, true);
            $versionArray = $response['result']['mappings']['variantIds'];
            if (!$versionArray) {
                return response()->json([
                    "data" => [
                        "status" => 101,
                        'dataSource' => $service,
                    ],
                    'status' => false,
                    'msg' => 'Version Id Not Found in Service...!',
                ]);
            }
            $version_id = '';
            $max_score = 0.0;

            if (isset($versionArray[0])) {
                foreach ($versionArray as $version) {
                    if ($version['score'] > $max_score) {
                        $max_score = $version['score'];
                        $version_id = strtoupper($version['variantId']);
                    }
                }
            }

            // If journey is of car and user is entering bike RC number then we don't have to save it to DB,
            if (isset($version_id)) {
                $sub_string = Str::substr($version_id, 0, 3);
                if (isset($array_type[$sub_string]) && $array_type[$sub_string] != trim($request->section)) {
                    // Delete the existing dirty data and again hit the signzy
                    DB::table('registration_details')->where('vehicle_reg_no', $request->registration_no)->delete();
                    $service = 'online';

                    $vehical_headers = [
                        'Content-Type' => $this->getCredential('vahan.signzy.attribute.content_type'),
                        'Authorization' => $this->getToken(),
                    ];

                    $vehical_data = httpRequestNormal($vehical_url, "POST", $vehical_body, [], $vehical_headers, [], false);
                    $url = $vehical_data['url'];
                    // $new_response = $vehical_data['response'];
                    $response = $vehical_data['response'];
                    // If journey is of car and user is entering bike RC number then we don't have to save it to DB,
                    if (isset($version_id)) {
                        $sub_string = Str::substr($version_id, 0, 3);
                        if (isset($array_type[$sub_string]) && $array_type[$sub_string] == trim($request->section)) {
                            DB::table('registration_details')->insert([
                                'vehicle_reg_no' => $request->registration_no,
                                'vehicle_details' => json_encode($response),
                                'created_at' => now(),
                                'updated_at' => now(),
                                'expiry_date' => $response['result']['vehicleInsuranceUpto'] ? Carbon::parse(str_replace('/', '-', $response['result']['vehicleInsuranceUpto']))->format('Y-m-d') : null,
                            ]);
                        }
                    }
                }
            }
        }

        $og_response = $response;
        $serialNumber = $og_response['result']['ownerCount'] ?? null;
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
            $insertedData = FastlaneRequestResponse::create([
                'enquiry_id' => $enquiryId,
                'request' => $request->registration_no,
                'response' => json_encode($og_response),
                'transaction_type' => "Signzy Service",
                'endpoint_url' => $url,
                'ip_address' => request()->ip(),
                'section' => $request->section,
                'response_time' => $responseTime->format('%H:%i:%s'),
                'created_at' => now(),
                'type' => 'input',
            ]);
        }

        if (!isset($response['result'])) {
            $message = $response['error']['message'] ?? 'Data not found.';
            $this->updateLogData($insertedData->id ?? null, 'Failed', $message);
            return response()->json([
                'status' => false,
                'msg' => $response['error']['message'] ?? null,
                'data'  => [
                    'status' => 101,
                ]
            ], 500);
        }

        $registration_date = date('d-m-Y', strtotime(str_replace('/', '-', $response['result']['regDate'])));
        $date1 = new DateTime($registration_date);
        $date2 = new DateTime();
        $interval = $date1->diff($date2);
        //as per git id removing validation https://github.com/Fyntune/motor_2.0_backend/issues/29828
        // if ($interval->y == 0 && $interval->m < 9) {
        //     $response['status'] = 101;
        //     $this->updateLogData($insertedData->id ?? null, 'Success');
        //     return response()->json([
        //         'status' => false,
        //         'msg' => 'Vehicle (' . $request->registration_no . ') not allowed within 9 Months. Vehicle Registration date is ' . $response['result']['regDate'] . ' ',
        //         'overrideMsg' => 'Vehicle (' . $request->registration_no . ') not allowed within 9 Months. Vehicle Registration date is ' . $response['result']['regDate'] . ' ',
        //     ]);
        // }

        $response = $response;
        $sectiontype = 'cv';

        $section_found = false;
        $vehicle_section = FastlaneVehDescripModel::where('description', $response['result']['class'])->first();
        // Currently Vehicle description is available for Car and bike
        // Description added for CV as well
        if (!empty($vehicle_section) && !empty($vehicle_section->section)) {
            $section_found = true;
            $sectiontype = $vehicle_section->section;
            if (in_array(trim($response['result']['class']), ['Private Service Vehicle']) && ($response['result']['vehicleCategory'] ?? '') == 'Individual Use') {
                $sectiontype = 'car';
            }
        } else if (self::findMyWord($response['result']['class'], 'LMV') || self::findMyWord($response['result']['class'], 'Motor Car') || self::findMyWord($response['result']['vehicleCategory'], '4WIC')) {
            $section_found = true;
            $sectiontype = 'car';
        } else if (self::findMyWord($response['result']['class'], 'SCOOTER') || self::findMyWord($response['result']['vehicleCategory'], '2WN')) {
            $section_found = true;
            $sectiontype = 'bike';
        }

        //finding cv type and vehicle category for updateProductSubtype & checkcvsectiontype function 22-02-24
        $finding_cv_type = trim($response['result']['class']);
        $vehicle_cat = trim($response['result']['vehicleCategory']);

        $cv_section_type = $this->checkCvSectionType($finding_cv_type, $vehicle_cat);
        $frontend_url = config('constants.motorConstant.' . strtoupper($sectiontype) . '_FRONTEND_URL');
        if (in_array($request->section, ['car', 'bike']) && $response['result']['isCommercial'] == 'true') {
            $data = [
                'ft_product_code' => 'cv',
                'redirectionUrl' => $frontend_url,
                'status' => 100,
                'dataSource' => $service,
            ];
            CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->update([
                'vehicle_registration_no' => $request->registration_no,
            ]);
            if ($cv_section_type['status'] && in_array($cv_section_type['cv_type'], ['pcv', 'gcv'])) {
                $this->updateProductSubtype($enquiryId, $cv_section_type['cv_type']);
                // $data['sub_section'] = $cv_section_type['cv_type'];
            }
            $this->updateLogData($insertedData->id ?? null, 'Success');
            return response()->json([
                'status' => true,
                'msg' => 'Mismatched vehicle type.',
                'data' => $data,
            ]);
        }

        $id = UserProductJourney::find($enquiryId)->product_sub_type_id ?? null;
        $parent_id = strtolower(get_parent_code($id));
        if ($section_found && ($sectiontype == 'cv' && $request->section == 'cv')) {
            $data = [
                'ft_product_code' => $sectiontype,
                'redirectionUrl' => $frontend_url,
                'status' => 100,
                'dataSource' => $service,
            ];
            CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->update([
                'vehicle_registration_no' => $request->registration_no,
            ]);
            if (!empty($parent_id) && $cv_section_type['status'] && ($parent_id != $cv_section_type['cv_type'])) {
                $this->updateProductSubtype($enquiryId, $cv_section_type['cv_type']);
                $data['sub_section'] = $cv_section_type['cv_type'];
                $this->updateLogData($insertedData->id ?? null, 'Success');
                return response()->json([
                    'status' => true,
                    'msg' => 'Mismatched vehicle type..',
                    'data' => $data
                ]);
            }
        }
        if ($section_found && ($sectiontype != $request->section)) {
            $data = [
                'ft_product_code' => $sectiontype,
                'redirectionUrl' => $frontend_url,
                'status' => 100,
                'dataSource' => $service,
            ];
            CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->update([
                'vehicle_registration_no' => $request->registration_no,
            ]);
            if ($cv_section_type['status'] && in_array($cv_section_type['cv_type'], ['pcv', 'gcv'])) {
                $this->updateProductSubtype($enquiryId, $cv_section_type['cv_type']);
                // $data['sub_section'] = $cv_section_type['cv_type'];
            }
            $this->updateLogData($insertedData->id ?? null, 'Success');
            return response()->json([
                'status' => true,
                'msg' => 'Mismatched vehicle type...',
                'data' => $data
            ]);
        }

        if(!$cv_section_type['status'] && !in_array($sectiontype, ['car', 'bike'])){
            return response()->json([
                'status' => false,
                'msg' => 'Could not verify cv section',
                'data'=> [
                    'status' => 108,
                    'showMessage' => 'Category not found, please contact RM.',
                    'class' => $finding_cv_type, 
                    'category' => $vehicle_cat
                ]
            ]);
        }

        if (!empty($version_id)) {
            switch (str::substr($version_id, 0, 3)) {
                case 'PCV':
                    $section_found = true;
                    $version_type = 'pcv';
                    $varient_sectiontype = 'cv';
                    break;
                case 'GCV':
                    $section_found = true;
                    $version_type = 'gcv';
                    $varient_sectiontype = 'cv';
                    break;
                case 'CRP':
                    $section_found = true;
                    $version_type = $varient_sectiontype = 'car';
                    break;
                case 'BYK':
                    $section_found = true;
                    $version_type = $varient_sectiontype = 'bike';
                    break;
                case 'MIS':
                    $section_found = true;
                    $version_type = 'misc';
                    $varient_sectiontype = 'cv';
                default:
                    $section_found = false;
            }

            if ($section_found && !empty($parent_id) && $parent_id != $version_type && in_array($version_type, ['pcv','gcv'])) {
                $frontend_url = config('constants.motorConstant.' . strtoupper($varient_sectiontype) . '_FRONTEND_URL');
                CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->update([
                    'vehicle_registration_no' => $request->registration_no,
                ]);
                $data = [
                    'ft_product_code' => $sectiontype,
                    'redirectionUrl' => $frontend_url,
                    'status' => 100,
                    'dataSource' => $service,
                ];
                if ($cv_section_type['status'] && in_array($cv_section_type['cv_type'], ['pcv', 'gcv'])) {
                    $this->updateProductSubtype($enquiryId, $cv_section_type['cv_type']);
                    $data['sub_section'] = $cv_section_type['cv_type'];
                }
                $this->updateLogData($insertedData->id ?? null, 'Success');
                return response()->json([
                    'status' => true,
                    'msg' => 'Mismatched vehicle type....',
                    'data' => $data,
                ]);
            }
        } else {
            $this->updateLogData($insertedData->id ?? null, 'Failed', 'Version Id Not Found in Service.');
            return response()->json([
                "data" => [
                    "status" => 101,
                    'dataSource' => $service,
                ],
                'status' => false,
                'msg' => 'Version Id Not Found in Service...!',
            ], 200);
        }

        // If bike RC number is entered on car page,
        // then show pop-up(pass 100 and parent status as true) based on vehicle description
        $frontend_url = config('constants.motorConstant.' . strtoupper($varient_sectiontype) . '_FRONTEND_URL');
        if ($section_found && $varient_sectiontype != $request->section) {
            $data = [
                'ft_product_code' => $sectiontype,
                'redirectionUrl' => $frontend_url,
                'status' => 100,
                'dataSource' => $service,
            ];
            CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->update([
                'vehicle_registration_no' => $request->registration_no,
            ]);
            if ($cv_section_type['status'] && in_array($cv_section_type['cv_type'], ['pcv', 'gcv'])) {
                $this->updateProductSubtype($enquiryId, $cv_section_type['cv_type']);
                // $data['sub_section'] = $cv_section_type['cv_type'];
            }
            $this->updateLogData($insertedData->id ?? null, 'Success');
            return response()->json([
                'status' => true,
                'msg' => 'Mismatched vehicle type.....',
                'data' => $data
            ]);
        }

        if (isset($version_id) && $service == 'online') {
            $sub_string = Str::substr($version_id, 0, 3);
            if (isset($array_type[$sub_string]) && $array_type[$sub_string] == trim($request->section)) {
                DB::table('registration_details')->insert([
                    'vehicle_reg_no' => $request->registration_no,
                    'vehicle_details' => json_encode($response),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'expiry_date' => $response['result']['vehicleInsuranceUpto'] ? Carbon::parse(str_replace('/', '-', $response['result']['vehicleInsuranceUpto']))->format('Y-m-d') : null,
                ]);
            }
        }

        if (empty($response['result']['class']) && !$section_found) {
            $version_initial = Str::substr($response['result']['class'], 0, 3);
            if (isset($array_type[$version_initial])) {
                $sectiontype = $array_type[$version_initial];
            }
        }

        $type = $version_type;

        $journeyType = empty(request()->journeyType) ? 'signzy' : base64_decode(request()->journeyType);
        $version_code = '';
        $version_id = $version_id;
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
                'status' => false,
                'message' => 'version id not found in mmv file for ' . $version_id,
            ];
        }
        $registration_date = date('d-m-Y', strtotime(str_replace('/', '-', $response['result']['regDate'])));
        if (isset($response['result']['vehicleInsuranceUpto']) && !empty($response['result']['vehicleInsuranceUpto'])) {
            $policy_expiry = date('d-m-Y', strtotime(str_replace('/', '-', $response['result']['vehicleInsuranceUpto'])));
            $date1 = new \DateTime($registration_date);
            //$date2 = new \DateTime($policy_expiry);
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
        if (Str::contains($response['result']['vehicleManufacturingMonthYear'] ?? '', '/')) {
            $manf_year = explode('/', $response['result']['vehicleManufacturingMonthYear']);
            $manf_year = implode('-', $manf_year);
            // $invoiceDate = '01-'.$manf_year;
        } else {
            $manf_year = date('m-Y', strtotime($registration_date));
        }
        $invoiceDate = $registration_date;
        $new_rc_number = $request->registration_no;
        $reg_no = explode('-', $new_rc_number);
        if ($reg_no[1] < 10 && $reg_no[0] == 'DL' && $journeyType != 'signzy') {
            $reg_no[1] = '0' . $reg_no[1];
        }
        $rto_code = implode('-', [$reg_no[0], $reg_no[1]]);
        $rto_name = MasterRto::where('rto_code', $rto_code)->pluck('rto_name')->first();
        // Fetch company alias from
        $previous_insurer = $previous_insurer_code = null;
        $expiry_date = Carbon::parse($policy_expiry);
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
        if ($expiry_date->diffInDays(now()) > config('VAHAN_POLICY_EXPIRY_DATE_RANGE_IN_DAYS', 45)) {
            $policy_expiry = '';
        }

        $fast_lane_product_code = $response['result']['vehicleCategory'] ?? '';
        $ft_product_code = $cv_type = '';
        $vehicle_section = FastlaneVehDescripModel::where('description', $response['result']['class'])->first();
        // Currently Vehicle description is available for Car and bike
        if (!empty($vehicle_section)) {
            $ft_product_code = $vehicle_section->section;
            $ft_product_code == 'cv' ? $cv_type = 'cv' : $cv_type = '';
        } else if (isset($response['result']['class']) && (stripos($response['result']['class'], 'LMV') !== false) || (stripos($response['result']['class'], 'Motor Car') !== false)) {
            $ft_product_code = 'car';
        } else if (isset($response['result']['class']) && stripos($response['result']['class'], 'SCOOTER') !== false) {
            $ft_product_code = 'bike';
        }
        if ($fast_lane_product_code == 'LMV') {
            $ft_product_code = 'car';
        } else if ($fast_lane_product_code == '2W') {
            $ft_product_code = 'bike';
        }
        if ($ft_product_code == 'car' || $ft_product_code == 'TAXI') {
            $od_compare_date = now()->subYear(2)->subDay(180);
        } else if ($ft_product_code == 'bike') {
            // $od_compare_date = Carbon::parse('01-09-2018')->subDay(1);
            $od_compare_date = now()->subYear(4)->subDay(180);
        }

        if (in_array($ft_product_code, ['car', 'bike'])) {
            // dd($registration_date, $od_compare_date, $policy_type = Carbon::parse($registration_date) > $od_compare_date ? 'own_damage' : 'comprehensive');
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

        $curlResponse = [];

        $ncb_previous_ic_popup_disabled = config('NCB_PREVIOUS_IC_POPUP_DISABLED') == 'Y';
        $ncb_previous_ic_popup_seller_types = [];

        $cv_agent_mappings = CvAgentMapping::where('user_product_journey_id', $enquiryId)->first();

        if (config('NCB_PREVIOUS_IC_POPUP_SELLER_TYPES')) {
            $ncb_previous_ic_popup_seller_types = explode(',', config('NCB_PREVIOUS_IC_POPUP_SELLER_TYPES'));

            if (!empty($cv_agent_mappings->user_id)) {
                array_push($ncb_previous_ic_popup_seller_types, null);
            }
        }

        if (isset($response['result']['vehicleInsuranceCompanyName']) && !empty($response['result']['vehicleInsuranceCompanyName'])) {
            $fyntune_ic = FastlanePreviousIcMapping::whereRaw('? LIKE CONCAT("%", identifier, "%")', $response['result']['vehicleInsuranceCompanyName'])->first();
            if ((!$ncb_previous_ic_popup_disabled || ($ncb_previous_ic_popup_disabled && !in_array($cv_agent_mappings?->seller_type, $ncb_previous_ic_popup_seller_types))) && $fyntune_ic) {
                $previous_insurer = $fyntune_ic->company_name;
                $previous_insurer_code = $fyntune_ic->company_alias;
            }
        }
        $mmv_details = get_fyntune_mmv_details(Str::substr($version_id, 0, 3), $version_code);
        if (!$mmv_details['status']) {
            return [
                'status' => false,
                'message' => 'MMV details not found. - ' . $version_code,
            ];
        }
        $mmv_details = $mmv_details['data'];
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
        if (!empty($mmv_details['product_sub_type_id'])) {
            $product_sub_type_id = $mmv_details['product_sub_type_id'];
            // $cv_type = $mmv_details['manufacturer']['cv_type'];
        } else {
            $product_sub_type_id = array_search($product, $producttype);
        }

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
        $inapp = false;
        $previous_insurance = '';
        if (!$inapp) {
            if ($previous_insurer) {
                $previous_insurance = $previous_insurer;
            } else {
                $previous_insurance = $response['result']['insurance_data']['company'] ?? '';
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
        $previous_policy_type = $policy_type == 'own_damage' ? null : 'Comprehensive';
        $corporate_vehicles_quote_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();
        CorporateVehiclesQuotesRequest::updateOrCreate(
            [
                'user_product_journey_id' => $enquiryId,
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
                'fuel_type' => $response['result']['type'] ?? $response['result']['type'], //'PETROL',
                'manufacture_year' => $manf_year,
                'vehicle_invoice_date' => $invoiceDate,
                "vehicle_owner_type" => "I",
                'rto_code' => isBhSeries($rto_code) ? null : $rto_code,
                'rto_city' => isBhSeries($rto_name) ? null : $rto_name,
                'previous_ncb' => $previous_ncb,
                'applicable_ncb' => $applicable_ncb,
                'previous_insurer' => $previous_insurance, // corrected variable name
                'previous_insurer_code' => $inapp ? '' : $previous_insurer_code,
                'journey_type' => $journeyType,
                'zero_dep_in_last_policy' => 'Y',
                'gcv_carrier_type' => in_array($product_sub_type_id, ['9', '13', '14', '15', '16']) ? 'PUBLIC' : null,
                'is_ncb_verified' => 'N',
            ]
        );

        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $quote_log_data = isset($quote_log->quote_data) ? json_decode($quote_log->quote_data, true) : [];

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
        ];
        $QuoteLog = [
            'user_product_journey_id' => $enquiryId,
            'quote_data' => json_encode($quote_data),
        ];

        QuoteLog::updateOrCreate([
            'user_product_journey_id' => $enquiryId,
        ], $QuoteLog);

        $curlResponse['results'][0]['vehicle']['regn_dt'] = $registration_date;
        $curlResponse['results'][0]['vehicle']['vehicle_cd'] = $save_mmv ? $version_code : $quote_log_data['version_id'];
        $curlResponse['results'][0]['vehicle']['fla_maker_desc'] = $quote_data['manfacture_name'];
        $curlResponse['results'][0]['vehicle']['fla_model_desc'] = $quote_data['model_name'];
        $curlResponse['results'][0]['vehicle']['fla_variant'] = $quote_data['version_name'];
        $curlResponse['results'][0]['insurance']['insurance_upto'] = date('d-m-Y', strtotime(str_replace('/', '-', $response['result']['vehicleInsuranceUpto'])));

        $name = explode(' ', $response['result']['owner']);
        $address = explode(',', $response['result']['permanentAddress']);
        preg_match_all('!\d+!', end($address), $matches);
        $pincode = null;
        if (!empty($response['result']['splitPresentAddress']['pincode']) && is_numeric($response['result']['splitPresentAddress']['pincode'])) {
            $pincode = (int) $response['result']['splitPresentAddress']['pincode'];
        }
        // If pincode is in wrong/dummy then don't store that value in DB - @Amit 03-09-2022 #8325
        if (\Illuminate\Support\Str::contains($pincode, '999999')) {
            $pincode = null;
        }
        $return_data = [
            'applicableNcb' => $applicable_ncb,
            'businessType' => $businessType,
            "product_sub_type_id" => $product_sub_type_id,
            'corpId' => '',
            'emailId' => '',
            'enquiryId' => customEncrypt($enquiryId),
            'firstName' => count($name) > 2 ? implode(' ', [$name[0], $name[1] ?? '']) : $name[0],
            'fuelType' => $quote_data['fuel_type'],
            'fullName' => $response['result']['owner'],
            'hasExpired' => "no",
            'isClaim' => "N",
            'isNcb' => "Yes",
            'lastName' => end($name),
            'leadJourneyEnd' => true,
            'manfactureId' => $quote_data['manfacture_id'],
            'manfactureName' => $quote_data['manfacture_name'],
            'manufactureYear' => $quote_data['manufacture_year'],
            'model' => $quote_data['model'],
            'modelName' => $quote_data['model_name'],
            'ownershipChanged' => "N",
            'policyExpiryDate' => $policy_expiry,
            'policyType' => $policy_type,
            'previousInsurer' => $previous_insurance,
            'previousInsurerCode' => $inapp ? '' : $previous_insurer_code,
            'previousNcb' => $previous_ncb,
            'previousPolicyType' => $previous_policy_type,
            'productSubTypeId' => $product_sub_type_id,
            "manufacture_year" => $manf_year,
            'rto' => isBhSeries($rto_code) ? null : $rto_code,
            'stage' => 11,
            'userProductJourneyId' => $enquiryId,
            'vehicleOwnerType' => "I",
            'vehicleRegisterAt' => isBhSeries($rto_code) ? null : $rto_code,
            'vehicleRegisterDate' => $registration_date,
            'vehicleRegistrationNo' => $new_rc_number,
            'vehicleInvoiceDate' => $invoiceDate,
            'vehicleUsage' => 2,
            'version' => $save_mmv ? $version_code : $quote_log_data['version_id'],
            'versionName' => $quote_data['version_name'],
            'address_line1' => $response['result']['permanentAddress'],
            // 'address_line1' => $address[0] ?? $response['owner_data']['permanent_address'],
            'address_line2' => '', // $address[1] ?? '', // address_line2 and address_line3 are not used in front end so complete address is not visible
            'address_line3' => '', // end($address) ?? '',
            'pincode' => ((int) $pincode > 0) ? (int) $pincode : null,
        ];

        $curlResponse['additional_details'] = $return_data;
        $curlResponse['additional_details']['vahan_service_code'] = self::VAHAN_SERVICE_CODE;

        $vehicle_color = removeSpecialCharactersFromString($response['result']['vehicleColour'] ?? null, true);
        //RenewBuy doesn't want to store the vehicle color #8325
        if (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') {
            $vehicle_color = null;
        }

        $is_vehicle_finance = $name_of_financer = null;
        if (!empty($response['result']['rcFinancer'])) {
            $is_vehicle_finance = 1;
            $name_of_financer = $response['result']['rcFinancer'];
        }

        $proposalData = [
            'first_name' => count($name) > 2 ? implode(' ', [$name[0], $name[1] ?? '']) : $name[0],
            'last_name' => end($name),
            'applicable_ncb' => $curlResponse['additional_details']['applicableNcb'],
            'is_claim' => 'N',
            'address_line1' => $curlResponse['additional_details']['address_line1'],
            'address_line2' => $curlResponse['additional_details']['address_line2'],
            'address_line3' => $curlResponse['additional_details']['address_line3'],
            'pincode' => $curlResponse['additional_details']['pincode'],
            'rto_location' => isBhSeries($rto_code) ? null : $rto_code,
            'vehicale_registration_number' => $new_rc_number,
            'vehicle_manf_year' => $curlResponse['additional_details']['manufacture_year'],
            'previous_insurance_company' => $response['result']['vehicleInsuranceCompanyName'] ?? null,
            'prev_policy_expiry_date' => empty($policy_expiry) ? null : date('d-m-Y', strtotime($policy_expiry)),
            'engine_number' => removeSpecialCharactersFromString($response['result']['engine']),
            'chassis_number' => $response['result']['chassis'],
            'previous_policy_number' => isset($response['result']['vehicleInsurancePolicyNumber']) ? $response['result']['vehicleInsurancePolicyNumber'] : '',
            'vehicle_color' => removeSpecialCharactersFromString($vehicle_color, true),
            'is_vehicle_finance' => $is_vehicle_finance,
            'name_of_financer' => $name_of_financer,
            'full_name_finance' => $name_of_financer,
            'puc_no' => $response['result']['puccNumber'] ?? null,
            'puc_expiry' => isset($response['result']['puccUpto']) ? date('d-m-Y', strtotime(str_replace('/', '-', $response['result']['puccUpto']))) :  null,
        ];

        if (config('constants.motorConstant.AUTO_FILL_TP_DETAILS_IN_VAHAN') == 'Y') {
            $tpExpiryDate = null;

            if (!empty($response['result']['vehicleInsuranceUpto'])) {
                $tpExpiryDate = date('d-m-Y', strtotime(str_replace('/', '-', $response['result']['vehicleInsuranceUpto'])));
            }

            $previousTpInsurer = $response['result']['vehicleInsuranceCompanyName'] ?? null;
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
                'tp_insurance_number'                   => $response['result']['vehicleInsurancePolicyNumber'] ?? null,
            ]);
        }

        UserProposal::updateOrCreate(
            ['user_product_journey_id' => $enquiryId],
            $proposalData
        );

        $curlResponse['status'] = 100;
        $curlResponse['ft_product_code'] = $sectiontype;
        $this->updateLogData($insertedData->id ?? null, 'Success');
        return response()->json([
            'service' => $service,
            'status' => true,
            "data" => $curlResponse,
        ]);
    }

    public function validateVehicleService(Request $request)
    {
        $userProductJourneyId = customDecrypt($request->enquiryId);
        $url = null;
        $existing_record = ProposalVehicleValidationLogs::select('response')
            ->where('vehicle_reg_no', $request->registration_no)
            ->where('service_type', 'signzy')
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
                $isResponseValid = (isset($response['result']['class']) && !empty($response['result']['class']));
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
            $regi_no = explode('-', $request->registration_no);
            $registratin_no = implode('', $regi_no);
            $startTime = new DateTime(date('Y-m-d H:i:s'));

            $vehical_url = $this->getCredential('vahan.signzy.attribute.registration.detailed.url');

            $mappingId = $this->getMappingId($request, $userProductJourneyId);
            if (empty($mappingId)) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Mapping id is not configured for ' . $request->section,
                    "data" => [
                        "status" => 101,
                        "vt" => $request->vt,
                    ],
                ]);
            }

            $vehical_headers = [
                'Content-Type' => $this->getCredential('vahan.signzy.attribute.content_type'),
                'Authorization' => $this->getToken(),
            ];

            
            $vehical_body = [
                "task" => "detailedSearch",
                "essentials" => [
                    "vehicleNumber" => $registratin_no,
                    "signzyID" => $mappingId,
                ],
            ];

            $vehical_data = httpRequestNormal($vehical_url, "POST", $vehical_body, [], $vehical_headers, [], false);
            $response = $vehical_data['response'];
            $endTime = new DateTime(date('Y-m-d H:i:s'));
            $responseTime = $startTime->diff($endTime);
            $url = $vehical_url;
            $og_response = $curlResponse = $response;
            $insertedData = FastlaneRequestResponse::create([
                'enquiry_id' => $userProductJourneyId,
                'request' => $request->registration_no,
                'response' => json_encode($curlResponse),
                'transaction_type' => "Signzy Service",
                'endpoint_url' => $url,
                'ip_address' => request()->ip(),
                'section' => trim($request->section),
                'response_time' => $responseTime->format('%H:%i:%s'),
                'created_at' => now(),
                'type' => 'proposal',
            ]);
        }

        if (!isset($curlResponse['result']['status'])) {
            $this->updateLogData($insertedData->id ?? null, 'Failed', 'Status key not found in response.');
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

        if (!isset($curlResponse['result']['mappings']['signzyID'])) {
            $this->updateLogData($insertedData->id ?? null, 'Failed', 'Mapping details not found.');
            return response()->json([
                'status' => false,
                'msg' => 'Mapping details not found.',
                'data' => [
                    'dataSource' => $service,
                    'status' => 101,
                ],
                'response' => $curlResponse,
            ]);
        }

        $category_description = trim($curlResponse['result']['class']);
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
        if (!empty($vehicle_section) &&  !empty($vehicle_section->section)) {
            $ft_product_code = trim($vehicle_section->section);
        } else if (stripos($category_description ?? '', 'LMV') !== false || stripos($category_description ?? '', 'Motor Car') !== false || stripos($curlResponse['result']['vehicleCategory'] ?? '', '4WIC') !== false )  {
            $ft_product_code = 'car';
        } else if (stripos($category_description ?? '', 'SCOOTER') !== false) {
            $ft_product_code = 'bike';
        }

        $finding_cv_type = trim($curlResponse['result']['class']);
        $vehicle_cat = trim($curlResponse['result']['vehicleCategory']);
        $frontend_url = config('constants.motorConstant.' . strtoupper($ft_product_code) . '_FRONTEND_URL');
        $id = UserProductJourney::find($userProductJourneyId)->product_sub_type_id ?? null;
        $parent_id = strtolower(get_parent_code($id));
        $cv_section_type = $this->checkCvSectionType($finding_cv_type, $vehicle_cat);
        $data = [
            'ft_product_code' => $ft_product_code,
            'redirectionUrl' => $frontend_url,
            'status' => 100,
            'dataSource' => $service,
        ];
  
        if(!$cv_section_type['status'] && !in_array($ft_product_code, ['car','bike'])){
            return response()->json([
                'status' => false,
                'msg' => 'Could not verify cv section',
                'data'=> [
                    'status' => 110,
                    'overrideMsg' => 'Category not found, please contact RM..',
                    'class' => $finding_cv_type, 
                    'category' => $vehicle_cat
                ]
            ]);
        }

        if ($cv_section_type['status'] && in_array($cv_section_type['cv_type'], ['pcv', 'gcv']) && isset($parent_id) && $parent_id != $cv_section_type['cv_type'] ) {
            $data['sub_section'] = $cv_section_type['cv_type'];
            return response()->json([
                'status' => true,
                'msg' => 'Mismatched vehicle type.....',
                'data' => $data
            ]);
        }

        


        if (empty($existing_record) && !empty($ft_product_code)) {
            ProposalVehicleValidationLogs::insert([
                'vehicle_reg_no' => $request->registration_no,
                'response' => json_encode($og_response),
                'service_type' => 'signzy',
                'endpoint_url' => $url,
                'created_at' => now(),
            ]);
        }

        $curlResponse['ft_product_code'] = $ft_product_code;
        $curlResponse['status'] = 101;
        $curlResponse['dataSource'] = $service;

        // If journey is of car and bike RC number is searched then we'll redirect it to bike page,
        // and again hit the service, for that we need to pass 'status' as 100 - @Amit:08-03-2022

        if (strtolower($ft_product_code) == strtolower($request->section)) {
            $versionArray = $curlResponse['result']['mappings']['variantIds'] ?? [];
            $version_id = '';
            $max_score = 0.0;
            if (isset($versionArray[0])) {
                foreach ($versionArray as $version) {
                    if ($version['score'] > $max_score) {
                        $max_score = $version['score'];
                        $version_id = isset($version['variantId']) ? strtoupper($version['variantId']) : '';
                    }
                }
            }

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
                // If FT code is empty we need to pass status code as 101 only in data array tag - @Amit:29-02-2024
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
        $this->updateLogData($insertedData->id ?? null, 'Success');
        return response()->json([
            'status' => true,
            'msg' => $curlResponse['status'] == 100 ? 'Record found' : 'Vehicle validation failed.',
            'data' => $curlResponse,
        ]);
    }

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

    public function getMappingId(Request $request, $enquiryId)
    {
        $mp_id = '';
        switch ($request->section) {
            case 'bike':
                $mp_id = $this->getCredential('vahan.signzy.attribute.mapping_id.bike');
                break;
            case 'car':
                $mp_id = $this->getCredential('vahan.signzy.attribute.mapping_id.car');
                break;
            case 'cv':
                // we'll get vt tag in the URL either from the dashboard or from the broker's b2c website - 17-02-2024
                $id = UserProductJourney::find($enquiryId)->product_sub_type_id ?? null;
                $parent_id = get_parent_code($id);
                switch ($parent_id) {
                    case 'PCV':
                        $mp_id = $this->getCredential('vahan.signzy.attribute.mapping_id.pcv');
                        break;
                    case 'GCV':
                        $mp_id = $this->getCredential('vahan.signzy.attribute.mapping_id.gcv');
                        break;
                    case 'MISC':
                        $mp_id = $this->getCredential('vahan.signzy.attribute.mapping_id.miscd');
                        break;
                    default:
                        $mp_id = $this->getCredential('vahan.signzy.attribute.mapping_id.cv');
                        break;
                }
                break;
        }
        return $mp_id;
    }

    public function updateProductSubtype($enquiryId, $cv_type)
    {
        UserProductJourney::where('user_product_journey_id', $enquiryId)->update([
            'product_sub_type_id' => ($cv_type == 'pcv' ? 6 : 9)
        ]);
    }

    public function checkCvSectionType($finding_cv_type, $vehicle_cat)
    {
        // fetch the cv_section
        $cv_section_type = FastlaneCvVehicleDescription::where('vehicle_class', $finding_cv_type)->where('vehicle_category', $vehicle_cat)->value('cv_section');
        
        // check if the class and category is already inserted or not
        $row_exists = FastlaneCvVehicleDescription::where([
            ['vehicle_class', '=', $finding_cv_type],
            ['vehicle_category', '=',  $vehicle_cat],
            ['cv_section', '=', null]
        ])->first();
        
        if (!$row_exists && !$cv_section_type) {
            FastlaneCvVehicleDescription::create([
                "cv_section" => null,
                "vehicle_class" => $finding_cv_type,
                "vehicle_category" => $vehicle_cat,
            ]);
        }
        return [
            'status' => $cv_section_type ? true : false,
            'cv_type' => $cv_section_type
        ];
    }


    public function getToken()
    {
        $headers = [
            "Content-Type" => $this->getCredential('vahan.signzy.attribute.content_type'),
        ];
        $body = [
            "username" => $this->getCredential('vahan.signzy.attribute.username'),
            "password" => $this->getCredential('vahan.signzy.attribute.password'),
        ];
        $url = $this->getCredential('vahan.signzy.attribute.login.url');
        $token = httpRequestNormal($url, "POST", $body, [], $headers, [], false);
        return $token['response']['id'];
    }
}
