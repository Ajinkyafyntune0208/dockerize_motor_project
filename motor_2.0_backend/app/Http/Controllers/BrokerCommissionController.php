<?php

namespace App\Http\Controllers;

use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\MasterCompany;
use App\Models\MasterRto;
use App\Models\UserProductJourney;
use DateTime;
use Illuminate\Http\Request;

use Illuminate\Support\Str;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BrokerCommissionController extends Controller
{
    public static function getRules($data, $confId, $retrospectiveChange = false)
    {
        //skip condition for retrospective change in commission config
        if (!$retrospectiveChange) {
            if (strtoupper($data['user_type_id']) != 4) {//b2c
                $userList = DB::connection('brocore')
                    ->table('brokerage_configurator_user_relation as bcur')
                    ->select('bcur.user_id')
                    ->where('bcur.conf_id', $confId)
                    ->get()
                    ->pluck('user_id')
                    ->toArray();
    
                if (!empty($userList) && !in_array($data['agent_id'], $userList)) {
                    return [];
                }
            }
        }

        $rules = DB::connection('brocore')
        ->table('brokerage_additional_filters as baf')
        ->select('baf.brokerage_additional_filters_id')
        ->where('baf.conf_id', $confId)
            ->orderBy('baf.priority')
            ->get()
            ->map(function ($item) {
                $item = (array) $item;
                return $item;
            })
            ->toArray();
        foreach ($rules as $key => $value) {
            $rules[$key]['brokerage'] = DB::connection('brocore')
            ->table('brokerage_additional_filters_bundle_brokerage as bafbb')
            ->select('bafbb.field_slug', 'bafbb.brokerage_type', 'bafbb.amount')
            ->where('bafbb.brokerage_additional_filters_id', $value['brokerage_additional_filters_id'])
            ->get()
            ->map(function ($item) {
                $item = (array) $item;
        
                $slug = $item['field_slug'];
                unset($item['field_slug']);
                
                return [$slug => $item];
            })
            ->reduce(function ($carry, $item) {
                return array_merge($carry, $item);
            }, []);
        }
        if (!empty($rules)) {
            $subRules = DB::connection('brocore')
            ->table('brokerage_additional_filters_bundle_relation as bafbr')
            ->select('bafbr.brokerage_additional_filters_bundle_relation_id', 'bafbr.brokerage_additional_filters_id', 'bafbr.field_slug', 'bafbr.operator')
            ->whereIn('bafbr.brokerage_additional_filters_id', array_column($rules, 'brokerage_additional_filters_id'))
            ->get()
                ->map(function ($item) {
                    $item = (array) $item;
                    return $item;
                })
                ->toArray();

            foreach ($subRules as $key => $value) {
                $subRules[$key]['value'] = DB::connection('brocore')
                ->table('brokerage_additional_filters_bundle_calculation as bafbc')
                ->select('bafbc.filter_value', 'bafbc.max_range', 'bafbc.min_range')
                ->where('bafbc.brokerage_additional_filters_bundle_relation_id', $value['brokerage_additional_filters_bundle_relation_id'])
                ->get()
                    ->map(function ($item) {
                        $item = (array) $item;
                        return $item;
                    })
                    ->toArray();
                unset($subRules[$key]['brokerage_additional_filters_bundle_relation_id']);
            }

            $subRulesById = [];
            foreach ($subRules as $subRule) {
                $output = $subRule;
                unset($output['brokerage_additional_filters_id']);
                $subRulesById[$subRule['brokerage_additional_filters_id']][] = $output;
            }

            return array_map(function ($rule) use ($subRulesById) {
                $rule['sub_rule'] = $subRulesById[$rule['brokerage_additional_filters_id']] ?? [];
                unset($rule['brokerage_additional_filters_id']);
                return $rule;
            }, $rules);
        }

        return [];
    }


    public function getCommissionRules(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        if (config('ENABLE_BROKERAGE_COMMISSION', 'N') != 'Y') {
            return response()->json([
                'status' => false,
                'message' => 'Brokerage Commission not enabled'
            ], 400);
        }

        if (config('BROKER_COMMISSION_RULE_TYPE') == 'API') {
            return BrokerCommissionApiController::getCommissionRules($request);
        }

        $broker = config('BROKER_ID_FOR_BROKERAGE_COMMISSION');
        if (empty($broker)) {
            return response()->json([
                'status' => false,
                'message' => 'Broker Id not present'
            ], 400);
        }

        $enquiryId = customDecrypt($request->enquiryId);

        $allIcs = MasterCompany::whereNotNull('company_alias')
        ->where([
            'status' => 'Active'
        ])
        ->get()
        ->pluck('company_alias')
        ->toArray();

        $icList = $request->icList;

        //testing purpose
        $isSample = config('USE_SAMPLE_IC_LIST_FOR_COMMISSION', 'N') == 'Y';

        if (empty($icList)) {
            $icList = $allIcs;
            $sampleIcList = [];

            if ($isSample) {
                $sampleIcList = json_decode(config('BROCRE_SAMPLE_IC_LIST'), true);
                $icList = array_keys($sampleIcList);
            }
        }

        $data = [
            'icList' => $icList
        ];
        
        //for retrospective change effective date will be policy issued date
        $effectiveDate = $request->retrospectiveChange ? $request->date : date('Y-m-d');

        $userProductJourney = UserProductJourney::with([
            'corporate_vehicles_quote_request',
            'sub_product',
            'sub_product.parent',
            'agent_details'
        ])->find($enquiryId)
        ->toArray();

        $corporateData = $userProductJourney['corporate_vehicles_quote_request'];
        $agentDetails = $userProductJourney['agent_details'][0] ?? [];

        $mmv = get_fyntune_mmv_details(
            $userProductJourney['product_sub_type_id'],
            $corporateData['version_id']
        );

        $registrationDate = new DateTime($corporateData['vehicle_register_date']);
        $expiryDate = new DateTime($effectiveDate);
        $interval = $registrationDate->diff($expiryDate);

        $vehicleAge = (($interval->y * 12) + $interval->m) + 1;
        $vehicleAge = floor($vehicleAge / 12);

        $rtoMaster = MasterRto::select('s.state_name', 'master_rto.rto_name', 'z.zone_name')
        ->join('master_state as s', 's.state_id', '=', 'master_rto.state_id')
        ->join('master_zone as z', 'z.zone_id', '=', 'master_rto.zone_id')
        ->where('rto_code', $corporateData['rto_code'])
        ->first();

        $rtoMaster = $rtoMaster ? $rtoMaster->toArray() : [];

        $input = [
            //agent id will be stored in brocore data base
            'agent_id' => $agentDetails['agent_id'] ?? '',

            'policyType' => $corporateData['business_type'] ?? '',
            // 'policyType' => $corporateData['policy_type'] ?? '',
            'manufactureYear' => $corporateData['manufacture_year'] ?? '',
            'vehicleAge' => $vehicleAge,
            'planType' => $corporateData['vehicle_owner_type'] == 'C' ? 'Company' : 'Individual',

            'rtocity' => $rtoMaster['rto_name'] ?? '',
            'rtostate' => $rtoMaster['state_name'] ?? '',
            'zone' => $rtoMaster['zone_name'] ?? '',
            'rto' => $corporateData['rto_code'] ?? '',


            'make' => $mmv['data']['manufacturer']['manf_name'] ?? '',
            'model' => $mmv['data']['model']['model_name'] ?? '',
            'fuelType' => $mmv['data']['version']['fuel_type'] ?? '',
            'cc' => $mmv['data']['version']['cubic_capacity'] ?? '',
            'vehicleSegment' => $mmv['data']['version']['body_type'] ?? '',
            'seatingCapacity' => $mmv['data']['version']['seating_capacity'] ?? '',

            'product_sub_type_parent' => $userProductJourney['sub_product']['parent']['product_sub_type_code'] ?? '',
            'product_sub_type' => $userProductJourney['sub_product']['product_sub_type_code'] ?? '',
            'gvw' => $mmv['data']['version']['gvw'] ?? ''
        ];

        if ($request->ignoreBusinessType) {
            unset($input['policyType']);
        }

        $input = array_map(function($v) {
            return strtoupper($v);
        }, $input);



        $commissionStructure = "PAY_OUT";
        $commissionType = 'BROKERAGE';

        if ($request->isPayIn) {
            $commissionStructure = "PAY_IN";
        }

        $userType = 4;//b2c
        if (!empty($agentDetails['seller_type'])) {
            $sellerTypeList = [
                'E' => 1,
                'P' => 2,
                'PARTNER' => 3,
                'B2C' => 4,
                'U' => 5,
                'MISP' => 6
            ];

            if (!empty($sellerTypeList[strtoupper($agentDetails['seller_type'])])) {
                $userType = $sellerTypeList[strtoupper($agentDetails['seller_type'])];
            }
        }

        $input['user_type_id'] = $userType;

        $data = array_merge($data, $input);

        $standardBrokerage = DB::connection('brocore')
        ->table('broker_configurator as bc')
        ->select('bc.conf_id', 'bcfr.amount', 'bcfr.brokerage_type', 'tfm.field_slug', 'mc.company_slug')
        ->join(
            'lob_master as lm',
            'lm.lob_id',
            '=',
            'bc.lob_id'
        )
        ->leftJoin(
            'brokerage_configurator_field_relation as bcfr',
            'bcfr.conf_id',
            '=',
            'bc.conf_id'
        )
        ->leftJoin(
            'transaction_field_master as tfm',
            'tfm.transaction_field_master_id',
            '=',
            'bcfr.transaction_field_master_id'
        )
        ->join(
            'master_company as mc',
            'mc.company_id',
            '=',
            'bc.company_id'
        )->where([
            // ['lm.lob_slug', '=', $data['product_sub_type']],
            ['lm.lob_slug', '=', $data['product_sub_type_parent']],
            ['bc.commission_type', '=', $commissionType],
            ['bc.commission_structure', '=', $commissionStructure],
            ['bc.user_types', '=', $userType],
            ['bc.effective_start_date', '<=', $effectiveDate],
            ['bc.effective_end_date', '>=', $effectiveDate],
            ['bc.broker_id', '=', $broker],
            ['bc.status', '=', 'Y'],
        ])
        ->whereIn('mc.company_slug', $data['icList']);

        //if there is a retrospective change in commission config
        if ($request->retrospectiveChange && $request->confId) {
            $standardBrokerage = $standardBrokerage->where('bc.conf_id', $request->confId);
        }

        $standardBrokerage = $standardBrokerage->get()
        ->map(function ($item) {
            return (array) $item;
        })
        ->toArray();

        $brokerConfig = [];

        $newStandardBrokerage = [];
        foreach($standardBrokerage as $s) {
            if (isset($brokerConfig[$s['conf_id']])) {
                array_push($brokerConfig[$s['conf_id']], $s);
            } else {
                $brokerConfig[$s['conf_id']] []= $s;
            }
        }

        $result = [];
        $confList = array_keys($brokerConfig);

        // store conf id if there is retrospective change also
        if (
            $request->storeConfId ||
            $request->retrospectiveChange
        ) {
            $confId = $confList[0] ?? null;

            $dataToUpdate = [];

            if ($request->isPayIn) {
                $dataToUpdate['payin_conf_id'] = $confId;
            } else {
                $dataToUpdate['commission_conf_id'] = $confId;
            }
            \App\Models\PremiumDetails::updateOrCreate([
                'user_product_journey_id' => $enquiryId
            ], $dataToUpdate);
        }

        foreach($confList as $confId) {
            //get rules for the conf id
            $rules = self::getRules($data, $confId, $request->retrospectiveChange);

            $allRules = $rules;

            //add rule identifer
            $allRules = self::validateRules($allRules, [], true);

            $valdatedRules = self::validateRules($rules, $data, true);

            $companyAlias = $brokerConfig[$confId][0]['company_slug'];

            if ($isSample && isset($sampleIcList[$companyAlias])) {
                $companyAlias = $sampleIcList[$companyAlias];
            }
            

            $standardBrokerage = $brokerConfig[$confId];

            //remove unneccessary elements
            $standardBrokerage = array_map(function($v) {
                unset(
                    $v['conf_id'],
                    $v['company_slug']
                );
                return $v;
            }, $standardBrokerage);

            $newStandardBrokerage = [];
            foreach ($standardBrokerage as $st) {
                if (!empty($st['field_slug'])) {
                    $slug = $st['field_slug'];
                    unset($st['field_slug']);
                    $newStandardBrokerage[$slug] = $st;
                }
            }

            if (!empty($valdatedRules || !empty($newStandardBrokerage))) {
                $result[$companyAlias] = self::finalResponse([
                    'rules' => $valdatedRules,
                    'brokerage' => $newStandardBrokerage
                ]);

                //all rules for the specific config
                if ($request->returnAllRules) {
                    $result[$companyAlias]['fullConfig'] = [
                        'rules' => $allRules,
                        'standardBrokerage' => $newStandardBrokerage
                    ];
                }
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Commission rules fetched successfully',
            'data'=> $result
        ], 200);
    }

    public static function validateRules($rules, $data, $ruleIdentifier = false)
    {
        $rulePrefix = 'motor_';
        foreach($rules as $rkey => $r) {
            if ($ruleIdentifier) {
                $rules[$rkey]['ruleIdentifier'] = $rulePrefix.$rkey + 1;
            }
            $subRule = $r['sub_rule'] ?? [];

            foreach ($subRule as $sKey => $s) {

                if (isset($rules[$rkey]['valid']) && !$rules[$rkey]['valid']) {
                    break;
                }

                if (isset($subRule[$sKey]['valid']) && !$subRule[$sKey]['valid']) {
                    $rules[$rkey]['valid'] = false;
                    break;
                }

                if (isset($data[$s['field_slug']])) {
                    $isArray = is_array($data[$s['field_slug']]);
                    $rules[$rkey]['valid'] = false;
                    $subRule[$sKey]['valid'] = false;
                    switch ($s['operator']) {
                        case 'RANGE':
                            if (!$isArray) {
                                $min = $s['value'][0]['min_range'];
                                $max = $s['value'][0]['max_range'];
                                if (
                                    $data[$s['field_slug']] >= $min && $data[$s['field_slug']] <= $max
                                ) {
                                    $subRule[$sKey]['valid'] = true;
                                }
                            }
                            break;

                        case 'EQUALS':
                            $val = array_column($s['value'], 'filter_value');
                            $val = array_map(function ($v) {
                                return strtoupper($v);
                            }, $val);
                            
                            if (!$isArray) {
                                if (in_array($data[$s['field_slug']], $val)) {
                                    $subRule[$sKey]['valid'] = true;
                                }
                            } else {
                                if (
                                    !empty($data[$s['field_slug']]) &&
                                    !empty(array_intersect($val, $data[$s['field_slug']]))
                                ) {
                                    $subRule[$sKey]['valid'] = true;
                                }
                            }
                            break;

                        case 'EXCLUDED':
                            $val = array_column($s['value'], 'filter_value');
                            $val = array_map(function ($v) {
                                return strtoupper($v);
                            }, $val);

                            if (!$isArray) {

                                if (!in_array($data[$s['field_slug']], $val)) {
                                    $subRule[$sKey]['valid'] = true;
                                }
                            } else {
                                if (
                                    !empty($data[$s['field_slug']]) &&
                                    empty(array_diff($val, $data[$s['field_slug']]))
                                ) {
                                    $subRule[$sKey]['valid'] = true;
                                }
                            }
                            break;

                        case 'CONTAINS':
                            $prefixes = array_column($s['value'], 'filter_value');
                            $prefixes = array_map(function ($v) {
                                return strtoupper($v);
                            }, $prefixes);

                            if (!$isArray) {
                                $check = collect($prefixes)
                                    ->contains(fn($prefix) => Str::contains(
                                        $data[$s['field_slug']],
                                        $prefix
                                    ));

                                if ($check) {
                                    $subRule[$sKey]['valid'] = true;
                                }
                            }
                            break;

                        case 'LESSTHAN':
                            $val = $s['value'][0]['filter_value'];

                            if (!$isArray) {
                                if ($data[$s['field_slug']] < $val) {
                                    $subRule[$sKey]['valid'] = true;
                                }
                            }
                            break;

                        case 'GREATERTHAN':
                            $val = $s['value'][0]['filter_value'];

                            if (!$isArray) {
                                if ($data[$s['field_slug']] > $val) {
                                    $subRule[$sKey]['valid'] = true;
                                }
                            }
                            break;

                        case 'STARTSWITH':
                            $prefixes = array_column($s['value'], 'filter_value');
                            $prefixes = array_map(function($v) {
                                return strtoupper($v);
                            }, $prefixes);

                            if (!$isArray) {
                                $check = collect($prefixes)
                                    ->contains(fn($prefix) => Str::startsWith(
                                        $data[$s['field_slug']],
                                        $prefix
                                    ));
    
                                if ($check) {
                                    $subRule[$sKey]['valid'] = true;
                                }
                            }
                            break;

                        case 'ENDSWITH':
                            $prefixes = array_column($s['value'], 'filter_value');
                            $prefixes = array_map(function($v) {
                                return strtoupper($v);
                            }, $prefixes);

                            if (!$isArray) {
                                $check = collect($prefixes)
                                    ->contains(fn($prefix) => Str::endsWith(
                                        $data[$s['field_slug']],
                                        $prefix
                                    ));
    
                                if ($check) {
                                    $subRule[$sKey]['valid'] = true;
                                }
                            }
                            break;
                    }

                    $rules[$rkey]['valid'] = $rules[$rkey]['sub_rule'][$sKey]['valid'] = $subRule[$sKey]['valid'] ?? false;
                }
            }
        }

        $rules = array_filter($rules, fn ($rule) => ($rule['valid'] ?? true));

        return array_map(function ($r) use ($data) {
            if (
                !empty($data['businessType']) &&
                !empty($r['brokerage'])
            ) {
                if ($data['businessType'] == 'THIRD_PARTY') {
                    unset(
                        $r['brokerage']['odPremium'],
                        $r['brokerage']['totalOdPayable']
                    );
                } elseif ($data['businessType'] == 'OWN_DAMAGE') {
                    unset($r['brokerage']['tpPremium']);
                }
            }
            unset($r['valid']);

            $r['sub_rule'] = array_filter($r['sub_rule'], function ($sub) {
                return isset($sub['valid']) ? !$sub['valid'] : true;
            });

            $r['sub_rule'] = array_map(function ($sub) {
                unset($sub['valid']);
                return $sub;
            }, $r['sub_rule']);
            $r['sub_rule'] = array_values($r['sub_rule']);
            return $r;
        }, $rules);
    }

    public static function attachRules(&$productData, $enquiryId, $returnAllRules = false, $extras = [])
    {
        $oldRequest = request()->input();
        try {
            $icList = [];
            foreach ($productData as $key => $value) {
                $icList = array_merge($icList, array_column($value, 'companyAlias'));
            }

            $icList = array_values(array_unique($icList));

            if (!empty($icList)) {
                $userProductJourneyId = customDecrypt($enquiryId);
                $businessType = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $userProductJourneyId)
                ->pluck('business_type')
                ->first();

                $request = new Request();
                $commissionPortalType = 'brocore';

                if (config('BROKER_COMMISSION_RULE_TYPE') == 'API') {
                    $commissionPortalType = 'api';
                }

                $rewardType = config('BROKER_COMMISSION_REWARD_TYPE', 'AMOUNT');
                
                //get the validated rules
                $request->replace([
                    'enquiryId' => $enquiryId,
                    'icList' => $icList,

                    //do not validate business type
                    'ignoreBusinessType' => true,

                    //this is used to return all the rules
                    'returnAllRules' => $returnAllRules,

                    //effective date
                    'date' => $extras['date'] ?? null,

                    //store the conf id in the data base
                    'storeConfId' => $extras['storeConfId'] ?? false,

                    //only for retrospective change in commission
                    'confId' => $extras['confId'] ?? null,
                    'retrospectiveChange' => $extras['retrospectiveChange'] ?? false,
                    'date' => $extras['policyDate'] ?? null,
                    'isPayIn' => $extras['isPayIn'] ?? false,

                    'transactionType' => $extras['transactionType'] ?? null
                ]);

                $rules = new self();
                $rules = $rules->getCommissionRules($request);

                if ($rules instanceof \Illuminate\Http\JsonResponse) {
                    $rules = json_decode($rules->getContent(), true);
                }
                if (!empty($rules['data'])) {
                    $allRules = $rules['data'];

                    foreach ($productData as $policyType => $policy) {
                        foreach ($policy as $key => $product) {
                            $productData[$policyType][$key]['commission'] = [];

                            $premiumType = strtolower($product['premiumTypeCode'] ?? '');
                            if (in_array($premiumType, ['breakin'])) {
                                $premiumType = 'comprehensive';
                            } elseif (in_array($premiumType, [
                                'short_term_3',
                                'short_term_6',
                                'short_term_3_breakin',
                                'short_term_6_breakin'
                            ])) {
                                $premiumType = 'short_term';
                            } elseif (in_array($premiumType, [
                                'own_damage',
                                'own_damage_breakin',
                            ])) {
                                $premiumType = 'own_damage';
                            } elseif (in_array($premiumType, [
                                'third_party',
                                'third_party_breakin',
                            ])) {
                                $premiumType = 'third_party';
                            }

                            if ($commissionPortalType == 'api') {
                                $data = BrokerCommissionApiController::getValidationData([
                                    'policyType' => $premiumType,
                                    'product' => $product
                                ], $businessType);
                            } else {

                                if (($product['isRenewal'] ?? '') == 'Y') {
                                    $businessType = 'RENEWAL';
                                }

                                $data = [
                                    //brocore end business type is premium type
                                    'businessType' => strtoupper($premiumType),
                                    'policyType' => strtoupper($businessType ?? ''),
                                    'productName' => strtoupper($product['productName'])
                                ];
                            }

                            if (!empty($allRules[$product['companyAlias']])) {
                                $productRule = $allRules[$product['companyAlias']];
                                $rules = $productRule['rules'] ?? [];

                                //validate the rules again
                                $rules = self::validateRules($rules, $data);
                                $productRule['rules'] = $rules;

                                if (!empty($productRule['rules']) || !empty($productRule['brokerage'])) {
                                    $productData[$policyType][$key]['commission'] = self::finalResponse(
                                        $productRule,
                                        strtoupper($product['premiumTypeCode'] ?? '')
                                    );
                                } else {
                                    $productData[$policyType][$key]['commission'] = [];
                                }
                            }

                            if (!empty($productData[$policyType][$key]['commission'])) {
                                $productData[$policyType][$key]['commission']['rewardType'] = $rewardType;
                            }
                        }
                    }
                }
                
            }
        } catch (\Throwable $th) {
            Log::error($th);
        }

        request()->replace($oldRequest);
    }

    public static function finalResponse($response, $premiumType = null)
    {
        //this block of code will prepare the response as per fornt end requirement.
        if (!empty($response['rules'])) {
            $brokerage = $response['brokerage'] ?? [];

            if (config('BROKER_COMMISSION_RULE_TYPE') == 'API') {
                
                $rules = $response['rules'];
                $filterValueReplace = [
                    'zero_dep' => 'Zero Depreciation',
                    'cpa' => 'Compulsory Personal Accident'
                ];

                foreach ($rules as &$rValue) {

                    if (empty($rValue['sub_rule'])) {
                        continue;
                    }

                    foreach ($rValue['sub_rule'] as $skey => &$sub) {
                        $filterValues = array_column($sub['value'], 'filter_value');

                        if (($sub['field_slug'] ?? '') === 'zero_dep' &&
                            $sub['operator'] === 'CONTAINS'
                        ) {
                            $sub['field_slug'] = 'addonPd';

                            if (in_array('Yes', $filterValues) && in_array('No', $filterValues)) {
                                unset($rValue['sub_rule'][$skey]);
                            } else {
                                $sub['operator'] = in_array('Yes', $filterValues) ? 'INCLUDED' : 'EXCLUDED';
                                $sub['value'] = [
                                    [
                                        'filter_value' => $filterValueReplace['zero_dep'],
                                        'max_range' => null,
                                        'min_range' => null,
                                    ]
                                ];
                            }
                        } elseif (($sub['field_slug'] ?? '') === 'cpa' && $sub['operator'] === 'CONTAINS') {
                            if (in_array('Yes', $filterValues) && in_array('No', $filterValues)) {
                                unset($rValue['sub_rule'][$skey]);
                            }
                        }
                    }
                }

                // Reset array keys in case of unsets
                $rules = array_map(function ($rule) {
                    if (isset($rule['sub_rule'])) {
                        $rule['sub_rule'] = array_values($rule['sub_rule']);
                    }
                    return $rule;
                }, $rules);

                $response['rules'] = $rules;
            }

            $response['rules'] = array_reduce($response['rules'], function ($carry, $value) use (&$brokerage, &$response) {
                if (empty($value['sub_rule'])) {
                    $response['qualifiedRule'] = $value['ruleIdentifier'] ?? null;
                    $brokerage = $value['brokerage'];
                } else {
                    $carry[] = $value;
                }
                return $carry;
            }, []);

            $response['brokerage'] = $brokerage;
        }

        if (!empty($premiumType) && !empty($response['brokerage'])) {
            if ($premiumType == 'THIRD_PARTY') {
                unset(
                    $response['brokerage']['odPremium'],
                    $response['brokerage']['totalOdPayable']
                );
            } elseif ($premiumType == 'OWN_DAMAGE') {
                unset($response['brokerage']['tpPremium']);
            }
        }
        return $response;
    }

    public static function calculateTotalCommission($premiumDetails, $commisionConfig)
    {
        try {
            $commission = 0;
            $brokerage = $commisionConfig['brokerage'] ?? [];

            //unverified rules are those are based on odPremium,Tp premium, etc
            if (!empty($commisionConfig['UnverifiedRules'])) {
                $rules['brokerage'] = $commisionConfig['brokerage'] ?? [];
                $rules['rules'] = self::validateRules($commisionConfig['UnverifiedRules'], $premiumDetails);

                if (!empty($rules['rules']) || !empty($rules['brokerage'])) {
                    $updatedCommission = \App\Http\Controllers\BrokerCommissionController::finalResponse($rules);
                    $brokerage = $updatedCommission['brokerage'];
                }
            }

            //calculate total commission premium here
            foreach ($brokerage as $premiumKey => $value) {
                if (isset($value['brokerage_type']) && !empty($value['brokerage_type'])) {
                    if (strtoupper($value['brokerage_type']) == 'VARIABLE') {
                        $percentage = $value['amount'];
                        $premium = $premiumDetails[$premiumKey] ?? 0;

                        $commission += $premium * ($percentage / 100);
                    } else {
                        $commission += $value['amount'];
                    }
                }
            }
        } catch (\Throwable $th) {
            Log::error($th);
        }

        return is_numeric($commission) ? round($commission, 2) : 0;
    }

    public static function saveCommissionDetails($enquiryId, $extras = [])
    {
        try {

            $isPayIn = $extras['isPayIn'] ?? false;

            if ($isPayIn && config('BROKER_COMMISSION_PAYIN_ALLOWED') != 'Y') {
                return false;
            }

            $commissionKey = $isPayIn ? 'payin_details' : 'commission_details';

            $userProductJourney = UserProductJourney::find($enquiryId);

            if (empty($userProductJourney) || $userProductJourney->lead_source == 'RENEWAL_DATA_UPLOAD') {
                return false;
            }

            $isRetrospectiveChange = $extras['retrospectiveChange'] ?? false;
            $fullConfigRule = $brokerage = $productData = [];

            //if there is retrospective change refer product data from the premium details table
            if ($isRetrospectiveChange) {
                $commisionDetails = \App\Models\PremiumDetails::where('user_product_journey_id', $enquiryId)
                ->select($commissionKey)
                ->pluck($commissionKey)
                ->first();
                $productData = $commisionDetails['productData'] ?? [];
            }

            if (empty($productData)) {
                $masterPolicyId = \App\Models\QuoteLog::where('user_product_journey_id', $enquiryId)
                ->pluck('master_policy_id')
                ->first();

                if (empty($masterPolicyId)) {
                    Log::info('Master Policy id not found for commission. Journey Id'. $enquiryId);
                    return false;
                }
                $productData = getProductDataByIc($masterPolicyId);

                if (empty($productData)) {
                    Log::info('Product data not found for commission. Journey Id'. $enquiryId);
                    return false;
                }

                $productData = json_decode(json_encode($productData), true);
                $productData = camelCase($productData);
            }

            $originalProductData = $productData;

            $productData = json_decode(json_encode($productData), true);

            //store the actual product data
            $originalProductData = $productData = camelCase($productData);

            $premiumType = $productData['premiumTypeCode'];
            $productData = [
                $premiumType => [
                    $productData
                ]
            ];

            //get the commission brokerage after validation
            \App\Http\Controllers\BrokerCommissionController::attachRules($productData, customEncrypt($enquiryId), true, $extras);
            $commission = $productData[$premiumType][0]['commission'] ?? [];
            if (!empty($commission)) {

                $fullConfigRule = $commission['fullConfig'] ?? [];

                //if there are rules for addons validate here
                if (!empty($commission['rules'])) {
                    $selectedAddons = \App\Models\SelectedAddons::where('user_product_journey_id', $enquiryId)
                    ->first();
                    $addons = $cpa = $accessories = $additionalCovers = $discounts = [];

                    if (!empty($selectedAddons->applicable_addons)) {
                        $addons = array_values(array_column($selectedAddons->applicable_addons, 'name'));
                    }

                    if (!empty($selectedAddons->compulsory_personal_accident)) {
                        $cpa = array_values(array_column($selectedAddons->compulsory_personal_accident, 'name'));
                    }

                    if (!empty($selectedAddons->accessories)) {
                        $accessories = array_values(array_column($selectedAddons->accessories, 'name'));
                    }

                    if (!empty($selectedAddons->additional_covers)) {
                        $additionalCovers = array_values(array_column($selectedAddons->additional_covers, 'name'));
                    }

                    if (!empty($selectedAddons->discounts)) {
                        $discounts = array_values(array_column($selectedAddons->discounts, 'name'));
                    }

                    $allAdons = array_merge(
                        $addons,
                        $cpa,
                        $accessories,
                        $additionalCovers,
                        $discounts
                    );

                    $allAdons = array_map(function ($v) {
                        if (strtoupper($v) == 'TPPD COVER') {
                            $v = 'TPPD DISCOUNT';
                        }
                        return strtoupper($v);
                    }, $allAdons);

                    $validator = [
                        'addonPd' => $allAdons,
                        'zero_dep' => in_array('Zero Depreciation', $allAdons) ? 'Yes' : 'No',
                        'cpa' => in_array('Compulsory Personal Accident', $allAdons) ? 'Yes' : 'No',
                    ];

                    $commission['rules'] = \App\Http\Controllers\BrokerCommissionController::validateRules(
                        $commission['rules'],
                        $validator
                    );

                    if (!empty($commission['rules']) || !empty($commission['brokerage'])) {
                        $commission = \App\Http\Controllers\BrokerCommissionController::finalResponse($commission);
                    }
                }

                $brokerage = $commission['brokerage'] ?? [];

                $updateCommission = [
                    $commissionKey => [
                        'brokerage' => $brokerage,
                        'fullConfig' => $fullConfigRule,

                        //this is will be useful for retrospective change sync
                        'productData' => $originalProductData
                    ]
                ];

                $updateCommission[$commissionKey]['qualifiedRule'] = $commission['qualifiedRule'] ?? null;

                if (!empty($commission['rules'])) {
                    $updateCommission[$commissionKey]['UnverifiedRules'] = $commission['rules'];
                }

                \App\Models\PremiumDetails::updateOrCreate(
                    ['user_product_journey_id' => $enquiryId],
                    $updateCommission
                );
            }

            $updateCommission = [
                $commissionKey => [
                    'brokerage' => $brokerage,
                    'fullConfig' => $fullConfigRule,

                    //this is will be useful for retrospective change sync
                    'productData' => $originalProductData
                ]
            ];

            $updateCommission[$commissionKey]['qualifiedRule'] = $commission['qualifiedRule'] ?? null;

            if (!empty($commission['rules'])) {
                $updateCommission[$commissionKey]['UnverifiedRules'] = $commission['rules'];
            }

            \App\Models\PremiumDetails::updateOrCreate(
                ['user_product_journey_id' => $enquiryId],
                $updateCommission
            );
        } catch (\Throwable $th) {
            Log::error($th);
        }
    }
}
