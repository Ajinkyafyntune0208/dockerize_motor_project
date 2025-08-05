<?php

namespace App\Http\Controllers;

use App\Models\CommissionApiLog;
use App\Models\UserProductJourney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BrokerCommissionApiController extends Controller
{
    public static function getCommissionRules(Request $request)
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

        $enquiryId = customDecrypt($request->enquiryId);
        
        $userProductJourney = UserProductJourney::with([
            'corporate_vehicles_quote_request',
            'sub_product',
            'sub_product.parent',
            'agent_details'
        ])->find($enquiryId)
        ->toArray();

        $corporateData = $userProductJourney['corporate_vehicles_quote_request'];
        $agentDetails = $userProductJourney['agent_details'][0] ?? [];
        $sellerType = strtoupper($agentDetails['seller_type'] ?? null);

        if (
            config('DISABLE_B2C_IN_COMMISSION_CALCULATION') == 'Y' &&
            (empty($sellerType) || in_array($sellerType, [
                'B2C'
            ]))
        ) {
            return [
                'status' =>  false,
                'message' => 'Commission is disbaled for b2c journey'
            ];
        }

        $mmv = get_fyntune_mmv_details(
            $userProductJourney['product_sub_type_id'],
            $corporateData['version_id']
        );

        $fuelType = strtoupper($corporateData['fuel_type']);

        $fuelCategory = [
            'PETROL' => 'Petrol',
            'DIESEL' => 'Diesel',
            'CNG' => 'CNG',
            'LPG' => 'LPG',
            'Electric' => 'Electric',
            'PETROL+CNG' => 'PETROL/CNG'
        ];

        $fuelType = $fuelCategory[$fuelType];
        
        $apiRequest = [
            'sub_product_type' => strtolower($userProductJourney['sub_product']['product_sub_type_code'] ?? ''),
            'seller_mobile' => $agentDetails['agent_mobile'] ?? '',
            'issue_date' => date('Y-m-d'),
            'ic_alias' => $request->icList,
            'vehicle_makeby' => $mmv['data']['manufacturer']['manf_name'] ?? '',
            'vehicle_model' => $mmv['data']['model']['model_name'] ?? '',
            'engine_cc' => $mmv['data']['version']['cubic_capacity'] ?? null,
            'manu_year' => explode('-', $corporateData['manufacture_year'])[1],
            'fuel_type' => $fuelType,
            'rto' => str_replace('-', '', $corporateData['rto_code']),
            'ncb' => $corporateData['applicable_ncb']
        ];

        $response = httpRequest(
            'commission-payout-api',
            $apiRequest,
            [],
            [],
            [],
            false
        );

        if (!empty($request->transactionType)) {
            CommissionApiLog::updateOrCreate([
                'user_product_journey_id' => $enquiryId,
                'type' => $request->isPayIn ? 'PAYIN' : 'PAYOUT',
                'transaction_type' => $request->transactionType
            ], [
                'url' => $response['url'],
                'request' => $response['request'],
                'response' => $response['response']
            ]);
        }

        if ($request->returnAllRules && !empty($response['response']['data'] ?? null)) {
            foreach ($response['response']['data'] as $key =>  $value) {
                $response['response']['data'][$key]['fullConfig'] = $value;
            }
        }
        return $response['response'] ?? null;
    }

    public static function getValidationData($data, $businessType)
    {
        $policyType = $data['policyType'];
        $product = $data['product'];
        $isZeroDep = $product['zeroDep'] == '0';

        if ($businessType == 'newbusiness') {
            $businessType = 'New/Fresh';
            if (
                $policyType == 'comprehensive' &&
                in_array($product['productSubTypeId'], [
                    1,
                    2
                ])
            ) {
                // bundle package
                $businessType = 'New/Bundle';
            }
        } elseif ($businessType == 'breakin') {
            $businessType = 'Used/Breakin';
        } else {
            $businessType = 'Rollover';
        }

        if (($product['isRenewal'] ?? '') == 'Y') {
            $businessType = 'RENEWAL';
        }

        $coverageList = [
            'comprehensive' => 'COMP',
            'own_damage' => 'SAOD',
            'third_party' => 'SATP'
        ];

        return [
            'transaction_type' => strtoupper($businessType),
            'coverage' => $coverageList[$policyType],
            'zero_dep' => $policyType != 'third_party' && $isZeroDep ? 'YES' : 'NO',
        ];
    }
}
