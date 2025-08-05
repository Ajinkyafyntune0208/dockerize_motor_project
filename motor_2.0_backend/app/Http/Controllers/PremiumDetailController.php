<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\PremiumDetailErrorReport;
use App\Models\CvJourneyStages;
use App\Models\JourneyStage;
use App\Models\MasterCompany;
use App\Models\PremiumDetails;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Validator;

class PremiumDetailController extends Controller
{
    /**
     * Sync Old premium details.
     * This function is called via artisan command, hence DB Query is not required.
     *
     * @param int $enquiry_id
     * @param int $product_sub_type_id
     * @param string $company_alias
     * @return array
     */
    public function syncOldPremiumDetails($enquiry_id, $product_sub_type_id, $company_alias)
    {
        pushTraceIdInLogs($enquiry_id);
        try {
            $folderName = match ($product_sub_type_id) {
                1 => 'Car',
                2 => 'Bike',
                default => 'Services'
            };

            $className = "\\App\\Http\\Controllers\\SyncPremiumDetail\\" . $folderName . "\\" . ucfirst(Str::camel($company_alias)) . "PremiumDetailController";
            if (class_exists($className) && method_exists($className, 'syncDetails')) {
                $controller = new $className();
                return $controller->syncDetails($enquiry_id);
            }
            return [
                'status' => false,
                'message' => 'Integration not yet done.',
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error occured while syncing the premium details : ' . $e);
            return [
                'status' => false,
                'message' => 'Error occured while syncing data.',
            ];
        }
    }

    /**
     * Manul Sync the old premium details with the logs present in the database.
     * This is only for one trace id
     *
     * @param \Illuminate\Http\Request $request
     * @param string|int $enquiry_id
     * @return \Illuminate\Http\Response
     */

    public function manualSyncPremiumDetails(Request $request, $enquiry_id)
    {
        $trace_id = customDecrypt($enquiry_id);

        $record = CvJourneyStages::select('j.user_product_journey_id', 'j.product_sub_type_id', 'ql.master_policy_id', 'mc.company_alias', 'cv_journey_stages.stage')
            ->join('user_product_journey as j', 'j.user_product_journey_id', 'cv_journey_stages.user_product_journey_id')
            ->join('quote_log as ql', 'ql.user_product_journey_id', 'j.user_product_journey_id')
            ->join('master_company as mc', 'mc.company_id', '=', 'ql.ic_id')
            ->where('j.user_product_journey_id', $trace_id)
            ->whereNotNull('j.product_sub_type_id')->first();

        if (empty($record)) {
            return response()->json([
                'status' => false,
                'message' => 'No record found for the shared trace id.',
            ]);
        }

        return $this->syncOldPremiumDetails($trace_id, $record->product_sub_type_id, $record->company_alias);
    }

    public static function verifyPremiumDetails($enquiryId)
    {
        $fullTraceId = null;
        try {
            $fullTraceId = customEncrypt($enquiryId);
            $premiumDetails = PremiumDetails::select('details')
            ->where('user_product_journey_id', $enquiryId)
                ->pluck('details')
                ->first();

            if (empty($premiumDetails)) {
                return [
                    'status' => false,
                    'trace_id' => $fullTraceId,
                    'data' => [
                        'type' => 'no_record',
                        'message' => 'Premium Details data not found'
                    ]
                ];
            } else {
                $totalA = self::getTotalAPremium($premiumDetails);

                $totalB = self::getTotalBPremium($premiumDetails);

                $totalC = self::getTotalCPremium($premiumDetails);

                $totalD = self::getTotalDPremium($premiumDetails);

                $totalOd = $totalA - $totalC + $totalD;

                $lowerBound = $totalOd - 5;
                $upperBound = $totalOd + 5;

                if (
                    !($premiumDetails['final_od_premium'] >= $lowerBound &&
                        $premiumDetails['final_od_premium'] <= $upperBound)
                ) {
                    return [
                        'status' => false,
                        'trace_id' => $fullTraceId,
                        'data' => [
                            'type' => 'missmatch',
                            'message' => 'Premium mismatch in total own damage. Please verify all the premium details.'
                        ]
                    ];
                }

                $totalTp = $totalB;
                $lowerBound = $totalTp - 5;
                $upperBound = $totalTp + 5;

                if (
                    !($premiumDetails['final_tp_premium'] >= $lowerBound &&
                        $premiumDetails['final_tp_premium'] <= $upperBound)
                ) {
                    return [
                        'status' => false,
                        'trace_id' => $fullTraceId,
                        'data' => [
                            'type' => 'missmatch',
                            'message' => 'Premium mismatch in total third party. Please verify all the premium details.'
                        ]
                    ];
                }

                $netPremium = $totalOd + $totalTp;

                $finalPremium = $netPremium + $premiumDetails['service_tax_amount'];

                $lowerBound = $premiumDetails['final_payable_amount'] - 5;
                $upperBound = $premiumDetails['final_payable_amount'] + 5;

                if (
                    !($finalPremium >= $lowerBound &&
                        $finalPremium <= $upperBound)
                ) {
                    return [
                        'status' => false,
                        'trace_id' => $fullTraceId,
                        'data' => [
                            'type' => 'missmatch',
                            'message' => 'Premium mismatch. Please verify all the premium details.'
                        ]
                    ];
                }

                $finalPayable = UserProposal::select('final_payable_amount')
                ->where('user_product_journey_id', $enquiryId)
                ->pluck('final_payable_amount')
                ->first();

                $finalPayable = round($finalPayable);

                $lowerBound = $finalPayable - 5;
                $upperBound = $finalPayable + 5;
                
                if (
                    !($finalPremium >= $lowerBound &&
                        $finalPremium <= $upperBound)
                ) {
                    return [
                        'status' => false,
                        'trace_id' => $fullTraceId,
                        'data' => [
                            'type' => 'missmatch',
                            'message' => 'Premium is not matching with the user proposal. Please verify the details.'
                        ]
                    ];
                }
            }

            return [
                'status' => true,
                'trace_id' => $fullTraceId
            ];
        } catch (\Throwable $th) {
            return [
                'status' => false,
                'trace_id' => $fullTraceId,
                'data' => [
                    'type' => 'error',
                    'stack_trace' => $th->getTraceAsString()
                ]
            ];
        }
    }

    public static function getTotalAPremium($premiumDetails)
    {
        $sectionA = [
            'basic_od_premium',
            'loading_amount',
            'electric_accessories_value',
            'non_electric_accessories_value',
            'bifuel_od_premium',
            'geo_extension_odpremium'
        ];

        $totalA = array_sum(array_intersect_key($premiumDetails, array_flip($sectionA)));

        if (!empty($premiumDetails['limited_own_premises_od'])) {
            $totalA -= $premiumDetails['limited_own_premises_od'];
        }

        return $totalA;
    }

    public static function getTotalBPremium($premiumDetails)
    {
        $sectionB = [
            'basic_tp_premium',
            'bifuel_tp_premium',
            'compulsory_pa_own_driver',
            'pa_additional_driver',
            'll_paid_driver',
            'll_paid_employee',
            'geo_extension_tppremium',
            'll_paid_conductor',
            'll_paid_cleaner',
            'unnamed_passenger_pa_cover'
        ];

        $totalB = array_sum(array_intersect_key($premiumDetails, array_flip($sectionB)));

        if (!empty($premiumDetails['limited_own_premises_tp'])) {
            $totalB -= $premiumDetails['limited_own_premises_tp'];
        }

        if (!empty($premiumDetails['tppd_discount'])) {
            $totalB -= $premiumDetails['tppd_discount'];
        }
        
        return $totalB;
    }

    public static function getTotalCPremium($premiumDetails)
    {
        $sectionC = [
            'anti_theft',
            'voluntary_excess',
            'other_discount',
            'ncb_discount_premium'
        ];

        return array_sum(array_intersect_key($premiumDetails, array_flip($sectionC)));
    }

    public static function getTotalDPremium($premiumDetails)
    {
        $sectionD = [
            'zero_depreciation',
            'road_side_assistance',
            'imt_23',
            'consumable',
            'key_replacement',
            'engine_protector',
            'ncb_protection',
            'tyre_secure',
            'return_to_invoice',
            'loss_of_personal_belongings',
            'eme_cover',
            'wind_shield',
            'accident_shield',
            'conveyance_benefit',
            'passenger_assist_cover',
            'motor_protection',
            'battery_protect',
            'additional_towing'
        ];

        return array_sum(array_intersect_key($premiumDetails, array_flip($sectionD)));
    }

    public static function getPremiumDetailConfigList()
    {
        $config = config('ALLOWED_ICS_AND_PRODUCT_FOR_PREMIUM_DETAILS_FOR_REPORTS');
        return json_decode($config, true);
    }

    public function premiumDetailReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from' => 'required|date_format:Y-m-d H:i:s',
            'to' => 'required|date_format:Y-m-d H:i:s|after_or_equal:from',
            'print_report' => 'nullable|boolean',
            'stage' => 'nullable|array'
        ]);

        if($validator->fails()){
            return response()->json(['status'=>false,'msg' => $validator->errors()]);
        }

        if ($request->print_report) {

            $stageList = [
                STAGE_NAMES['PROPOSAL_ACCEPTED'],
                STAGE_NAMES['PAYMENT_INITIATED'],
                STAGE_NAMES['PAYMENT_SUCCESS'],
                STAGE_NAMES['PAYMENT_FAILED'],
                STAGE_NAMES['POLICY_ISSUED'],
                STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
            ];

            if (!empty($request->stage)) {
                $stageList = $request->stage;
            }

            $result = [];
            $icList = MasterCompany::whereNotNull('company_alias')
            ->get()
            ->pluck('company_alias', 'company_id')
            ->toArray();
            JourneyStage::select('user_product_journey_id', 'stage', 'ic_id')
            ->whereBetween('updated_at', [$request->from, $request->to])
            ->whereIn('stage', $stageList)
            ->with(['user_product_journay'])
            ->chunk(500, function ($journeys) use (&$result, $icList) {
                foreach ($journeys as $journey) {
                    if (
                        !empty($journey->user_product_journay) &&
                        $journey->user_product_journay->lead_source != 'RENEWAL_DATA_UPLOAD'
                    ) {
                        $content = PremiumDetailController::verifyPremiumDetails($journey->user_product_journey_id);
                        if (!$content['status']) {
                            $result[$content['trace_id']] = [
                                'ic' => $icList[$journey->ic_id] ?? null,
                                'stage' => $journey->stage,
                                'message' => $content['data']['stack_trace'] ?? $content['data']['message'] ?? null
                            ];
                        }
                        
                    }
                }
            });

            return response()->json([
                'status' => true,
                'data' => $result
            ], 201);
        } else {
            PremiumDetailErrorReport::dispatch($request->input());
            return response()->json([
                'status' => true,
                'message' => 'The report will be shared through email once it is prepared.'
            ]);
        }
    }

    public function premiumSync(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => 'array|required',
        ]);

        if($validator->fails()){
            return response()->json(['status'=>false,'msg' => $validator->errors()]);
        }

        $traceIdList =$request->enquiryId;

        $result = [];
        foreach ($traceIdList as $enquiryId) {
            $instance = new self();
            $request->replace([
                'enquiryId' => $enquiryId
            ]);
            $res = $instance->manualSyncPremiumDetails($request, $enquiryId);

            if ($res instanceof \Illuminate\Http\JsonResponse) {
                $res = json_decode($res->getContent(), true);
            }
            $result[$enquiryId] = $res;
        }

        return response([
            'status' => true,
            'data' => $result
        ], 201);
    }
}