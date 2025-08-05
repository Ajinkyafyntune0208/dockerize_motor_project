<?php

namespace App\Http\Controllers\VahanService;

use App\Http\Controllers\Controller;
use App\Models\FastlaneRequestResponse;
use App\Models\UserProductJourney;
use App\Models\VahanServiceLogs;
use function PHPUnit\Framework\fileExists;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\VahanServicePriorityList;
use App\Models\MasterCompany;

class VahanServiceController extends Controller
{
    public $vehicleType = false;
    protected $vahanConfiguration = [];

    public function hitVahanService(Request $request)
    {
        $stage = 'quote';
        if ($request->vehicleValidation == 'Y') {
            $stage = 'proposal';
            $response = $this->ProposalValidateVahanService($request);
        } else {
            $response = $this->QuoteVahanService($request);
        }
        $insert_vahan_data = [
            'vehicle_reg_no' => $request->registration_no,
            'enquiry_id' => customDecrypt($request->enquiryId),
            'stage' => $stage,
            'request' => json_encode($request->all(), JSON_PRETTY_PRINT),
            'response' => json_encode($response, JSON_PRETTY_PRINT),
            'status' => 'Active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        VahanServiceLogs::create($insert_vahan_data);
        return $response;
    }

    public function QuoteVahanService(Request $request)
    {
        $this->vehicleType = $this->getVehicleType(customDecrypt($request->enquiryId), $request->section);
        if (!$this->vehicleType) {
            return response()->json([
                'status' => false,
                'message' => 'Vehicle category not found for this trace ID.',
            ]);
        }
        $configurationDetails = DB::table('vahan_service_priority_list as p')
            ->join('vahan_service_list as l', 'l.id', '=', 'p.vahan_service_id')
            ->where('p.vehicle_type', $this->vehicleType)
            ->where('p.journey_type', 'input_page')
            ->where('l.status', 'Active')
            ->where('p.priority_no', '!=', 0)
            ->get();
        if ($configurationDetails->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Input Page vahan service is not configured for ' . $this->vehicleType . ' Vehicle',
            ]);
        }
        $executionType = $configurationDetails[0]->integration_process;
        $this->vahanConfiguration = $configurationDetails->sortBy('priority_no');
        if (in_array($executionType, ['priority', 'single'])) {
            return $this->QuoteVahanServiceListExecution($request);
        } else if ($executionType == 'parallel') {
            return $this->QuoteVahanServiceListExecution($request);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'No Vahan Service Activated',
                'data' => null,
            ]);
        }
    }

    public function QuoteVahanServiceListExecution(Request $request)
    {
        $enquiryId = customDecrypt($request->enquiryId);
        $journeyType = \App\Models\CvAgentMapping::where('user_product_journey_id', $enquiryId)->select('seller_type')->first()?->seller_type;
        $jTypes = [
            'partner' => 'B2B_partner_decision',
            'p' => 'B2B_pos_decision',
            'e' => 'B2B_employee_decision',
        ];
        $userJourneyType = $journeyType ? ($jTypes[strtolower($journeyType)] ?? 'B2C_decision') : 'B2C_decision';

        $service_list = [];
        $vahan_service_list_count = count($this->vahanConfiguration);
        $list = 0;
        foreach ($this->vahanConfiguration as $key => $services) {
            // On input page, value stored in column will be either true or false
            $isBusinessAllowed = json_decode($services->{$userJourneyType});
            $className = "\\App\\Http\\Controllers\\VahanService\\" . ucwords($services->vahan_service_name_code) . "ServiceController";
            if (fileExists($className) && method_exists($className, 'getVahanDetails')) {
                $controller = new $className();
                try {
                    if ($isBusinessAllowed === true) {
                        $service_list[$services->vahan_service_name_code] = $data = $controller->getVahanDetails($request);
                    } else {
                        $data = [
                            'status' => false,
                            'msg' => 'Vahan service is not configured for this business type : ' . ucwords(trim(str_replace(['_', 'decision'], [' ', ''], $userJourneyType))),
                            'data' => [
                                'status' => 101,
                            ],
                        ];
                        $service_list[$services->vahan_service_name_code] = $data;
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::info('Error occured while fetching the vahan details : ' . $e);
                    $data = [
                        'status' => false,
                        'msg' => 'Error occured while fetching the vahan details',
                        'data' => [
                            'status' => 101,
                        ],
                        'errorMsg' => $e->getMessage() . '. Line No. : ' . $e->getLine(),
                    ];
                    $service_list[$services->vahan_service_name_code] = $data;
                }
            } else {
                $data = [
                    'status' => false,
                    'msg' => 'Invalid Vahan Service',
                    'data' => [
                        'status' => 101,
                    ],
                ];
            }
            $data = is_array($data) ? $data : json_decode(json_encode($data->original), true);
            $data['servicelist'] = $service_list;
            if ($data['status'] && $data['data']['status'] == 100) {
                return $data;
            } else if (++$list == $vahan_service_list_count) {
                return $data;
            }
        }
    }

    public function ProposalValidateVahanService(Request $request)
    {
        $this->vehicleType = $this->getVehicleType(customDecrypt($request->enquiryId), $request->section);
        if (!$this->vehicleType) {
            return response()->json([
                'status' => false,
                'message' => 'Vehicle category not found for this trace ID.',
            ]);
        }
        $configurationDetails = DB::table('vahan_service_priority_list as p')
            ->join('vahan_service_list as l', 'l.id', '=', 'p.vahan_service_id')
            ->where('p.vehicle_type', $this->vehicleType)
            ->where('p.journey_type', 'proposal_page')
            ->where('l.status', 'Active')
            ->where('p.priority_no', '!=', 0)
            ->get();
        if ($configurationDetails->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Proposal page vahan service is not configured for ' . $this->vehicleType . ' Vehicle',
            ]);
        }
        $executionType = $configurationDetails[0]->integration_process;
        $this->vahanConfiguration = $configurationDetails->sortBy('priority_no');
        if (in_array($executionType, ['priority', 'single'])) {
            return $this->ProposalValidateVahanServiceListExecution($request);
        } else if ($executionType == 'parallel') {
            return $this->ProposalValidateVahanServiceListExecution($request);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'No Vahan Service Activated',
                'data' => null,
            ]);
        }
    }
    public function ProposalValidateVahanServiceListExecution(Request $request)
    {
        $service_list = [];
        $vahan_service_list_count = count($this->vahanConfiguration);
        $enquiryId = customDecrypt($request->enquiryId);
        $selectedIc = \App\Models\QuoteLog::join('master_policy as p', 'p.policy_id', '=', 'quote_log.master_policy_id')->where('user_product_journey_id', $enquiryId)->select('p.insurance_company_id')->first();
        if (empty($selectedIc->insurance_company_id)) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to find the selected Insurance Company on proposal page.',
            ]);
        }
        $selectedIc = $selectedIc->insurance_company_id;
        $journeyType = \App\Models\CvAgentMapping::where('user_product_journey_id', $enquiryId)->select('seller_type')->first()?->seller_type;
        $jTypes = [
            'partner' => 'B2B_partner_decision',
            'p' => 'B2B_pos_decision',
            'e' => 'B2B_employee_decision',
        ];
        $userJourneyType = $journeyType ? ($jTypes[strtolower($journeyType)] ?? 'B2C_decision') : 'B2C_decision';
        $list = 0;
        foreach ($this->vahanConfiguration as $key => $services) {
            // On proposal page, value stored in column will be in json format
            $columnData = json_decode($services->{$userJourneyType});
            $allowedIcs = explode(',', $columnData->allowedICs);
            $blockFailure = explode(',', $columnData->blockFailure);
            $className = "\\App\\Http\\Controllers\\VahanService\\" . ucwords($services->vahan_service_name_code) . "ServiceController";
            if (fileExists($className) && method_exists($className, 'validateVehicleService')) {
                $controller = new $className();
                try {
                    if (in_array($selectedIc, $allowedIcs)) {
                        $service_list[$services->vahan_service_name_code] = $data = $controller->validateVehicleService($request);
                    } else {
                        $data = [
                            'status' => false,
                            'msg' => 'Vahan service is not configured for the selected IC ID : ' . $selectedIc,
                            'data' => [
                                'status' => 101,
                            ],
                        ];
                        $service_list[$services->vahan_service_name_code] = $data;
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::info('Error occured while fetching proposal vahan details : ' . $e);
                    $data = [
                        'status' => false,
                        'msg' => 'Error occured while fetching the vahan details',
                        'data' => [
                            'status' => 101,
                        ],
                        'errorMsg' => $e->getMessage() . '. Line No. : ' . $e->getLine(),
                    ];
                    $service_list[$services->vahan_service_name_code] = $data;
                }
            } else {
                $data = [
                    'status' => false,
                    'msg' => 'Invalid Vahan Service : ' . $className . '->validateVehicleService',
                    'data' => [
                        'status' => 101,
                    ],
                ];
            }
            $data = is_array($data) ? $data : json_decode(json_encode($data->original), true);
            $data['servicelist'] = $service_list;
            if ($data['status'] && $data['data']['status'] == 100) {
                return $data;
            } else if (++$list == $vahan_service_list_count) {
                if (in_array($selectedIc, $blockFailure)) {
                    $data['data']['status'] = 102;

                    $data['message'] = 'Record not found. Block journey.';
                    if ($data['data']['showErrorMsg'] ?? false) {
                        $data['data']['status'] = 110;
                        $data['message'] = $data['data']['overrideMsg'];
                    }

                    if (isset($data['msg'])) {
                        $data['message2'] = $data['msg'];
                        unset($data['msg']);
                    }
                }
                return $data;
            }
        }
    }

    public function getVehicleType($traceId, $section)
    {
        $productSubTypeId = UserProductJourney::find($traceId)?->sub_product?->parent_product_sub_type_id;
        $type = [
            1 => '4W',
            2 => '2W',
            4 => 'CV',
            8 => 'CV',
        ];
        if (($type[$productSubTypeId] ?? false) == false) {
            $vehicleSections = [
                'car' => '4W',
                'bike' => '2W',
                'cv' => 'CV',
            ];
            return $vehicleSections[trim($section)] ?? false;
        }
        return $type[$productSubTypeId] ?? false;
    }

    /**
     * Update the fastlane log record status whether it is a failed or success entry
     * @param $id The primary id of the fastlane_request_response table
     * @param $status The status of the log. It should be either 'Failed', 'Success'
     * @param $message The error message of the failed case
     */
    public function updateLogData(Int $id = null, String $status = null, String $message = null)
    {
        if (!empty($id)) {
            FastlaneRequestResponse::find($id)->update([
                'status' => $status,
                'message' => $message,
            ]);
        }
    }
    public function getVahanServiceList()
    {
        try {
            $returnData = [];
            $vahan_service_list = VahanServicePriorityList::join('vahan_service_list', 'vahan_service_priority_list.vahan_service_id', '=', 'vahan_service_list.id')
                ->where('vahan_service_list.status', 'Active')
                ->select(
                    'vahan_service_priority_list.journey_type',
                    'vahan_service_priority_list.vehicle_type',
                    'vahan_service_priority_list.B2B_employee_decision',
                    'vahan_service_priority_list.B2B_pos_decision',
                    'vahan_service_priority_list.B2B_partner_decision',
                    'vahan_service_priority_list.B2C_decision',
                    'vahan_service_list.vahan_service_name_code'
                )
                ->orderby('vahan_service_priority_list.priority_no')
                ->get();

            $vehicleTypes = [
                '2W' => 'bike',
                '4W' => 'car',
                'CV' => 'cv'
            ];

            $masterCompanies = MasterCompany::whereNotNull('company_alias')
                ->where('status', 'active')
                ->pluck('company_alias', 'company_id')
                ->toArray();

            foreach ($vahan_service_list as $value) {
                if (!isset($vehicleTypes[$value->vehicle_type])) {
                    continue;
                }
                if ($value->journey_type == 'proposal_page') {
                    $returnData[$vehicleTypes[$value->vehicle_type]]['proposal']['serviceType'][] = $value->vahan_service_name_code;

                    $returnData[$vehicleTypes[$value->vehicle_type]]['proposal']['E'] = self::vahanConfigHelper(
                        json_decode($value->B2B_employee_decision, true),
                        $masterCompanies
                    );

                    $returnData[$vehicleTypes[$value->vehicle_type]]['proposal']['P'] = self::vahanConfigHelper(
                        json_decode($value->B2B_pos_decision, true),
                        $masterCompanies
                    );

                    $returnData[$vehicleTypes[$value->vehicle_type]]['proposal']['Partner'] = self::vahanConfigHelper(
                        json_decode($value->B2B_partner_decision, true),
                        $masterCompanies
                    );

                    $returnData[$vehicleTypes[$value->vehicle_type]]['proposal']['B2C'] = self::vahanConfigHelper(
                        json_decode($value->B2C_decision, true),
                        $masterCompanies
                    );
                } else {
                    $returnData[$vehicleTypes[$value->vehicle_type]]['input']['serviceType'][] = $value->vahan_service_name_code;

                    if ($value->B2B_employee_decision == 'true') {
                        $returnData[$vehicleTypes[$value->vehicle_type]]['input']['sellerTypeList'][] = 'E';
                    }
                    if ($value->B2B_pos_decision == 'true') {
                        $returnData[$vehicleTypes[$value->vehicle_type]]['input']['sellerTypeList'][] = 'P';
                    }
                    if ($value->B2B_partner_decision == 'true') {
                        $returnData[$vehicleTypes[$value->vehicle_type]]['input']['sellerTypeList'][] = 'Partner';
                    }
                    if ($value->B2C_decision == 'true') {
                        $returnData[$vehicleTypes[$value->vehicle_type]]['input']['sellerTypeList'][] = 'B2C';
                    }
                    if (!empty($returnData[$vehicleTypes[$value->vehicle_type]]['input']['sellerTypeList'])) {
                        $returnData[$vehicleTypes[$value->vehicle_type]]['input']['sellerTypeList'] = array_unique($returnData[$vehicleTypes[$value->vehicle_type]]['input']['sellerTypeList']);
                    }
                }
            }
            return $returnData;
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function vahanConfigHelper($data, $masterCompanies)
    {
        $res = [];
        if (!empty($data['allowedICs'])) {
            $allowedICs = explode(',', $data['allowedICs']);
            $blockFailure = $data['blockFailure'] ? explode(',', $data['blockFailure']) : [];
            foreach ($masterCompanies as $key => $Value) {
                if (in_array($key, $allowedICs)) {
                    $res[$Value] = [
                        'failure' => in_array($key, $blockFailure),
                    ];
                }
            }
        }
        return $res;
    }
    public function newBreakinLogicHandle($vehicleRegistrationDate,$manufactureDate)
    {
        $vehicleRegistrationDate = \Carbon\Carbon::createFromFormat('d-m-Y', $vehicleRegistrationDate);
        $manufactureDate = \Carbon\Carbon::createFromFormat('d-m-Y', $manufactureDate);
        $currentDate = \Carbon\Carbon::now();
        $diffInDays2 = $currentDate->diffInDays($manufactureDate);
        $diffInMonths = $currentDate->diffInMonths($vehicleRegistrationDate);
        
        if ($diffInMonths < 9 && $diffInDays2 > 320) {
           $returnData = [
            'policy_type' => 'own_damage',
            'policy_expiry' => '',
            'previous_insurer_code' => '',
            'previous_insurer' => '',
            'previous_policy_type' => ''
           ];
        }
        else
        {
            $returnData = [];
        }
        
        return $returnData;

    }
}
