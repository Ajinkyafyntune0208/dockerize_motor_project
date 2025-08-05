<?php

namespace App\Http\Controllers;

use App\Models\AgentDiscountConfiguration;
use App\Models\AgentMasterDiscount;
use App\Models\CvAgentMapping;
use App\Models\MasterCompany;
use App\Models\SelectedAddons;
use App\Models\UserProductJourney;
use Illuminate\Http\Request;

class AgentDiscountController extends Controller
{
    public static function getDiscounts(Request $request)
    {
        $enquiryId = customDecrypt($request->enquiryId);
        if (config('ENABLE_DISCOUNTING') == 'Y' && self::isUserApplicable($enquiryId)) {
            
            $productSubTypeId = UserProductJourney::where('user_product_journey_id', $enquiryId)
            ->first()
            ->product_sub_type_id;

            $vehicleType = strtolower(get_parent_code($productSubTypeId));

            if (empty($vehicleType)) {
                return ['status' =>  false];
            }

            
            $discountList = [
                'min' => 0,
                'max' => 0
            ];
            
            $selectedConfigId = AgentDiscountConfiguration::where('setting_name', 'activation.applicable_configuration')
            ->first()->value ?? null;
            if ($selectedConfigId) {
                $configMethods = AgentMasterDiscount::find($selectedConfigId);

                if ($configMethods->discount_code ?? null) {
                    switch ($configMethods->discount_code) {
                        case 'global_configuration':
                            $maxDiscount = AgentDiscountConfiguration::where('setting_name', 'global_config.global_percentage_for_all')
                            ->first()->value ?? 0;

                            $discountList['max'] = (float) $maxDiscount;
                            break;

                        case 'vehicle_wise':
                            $configList = AgentDiscountConfiguration::where('setting_name', 'vehicle_wise.'.$vehicleType)
                            ->first();

                            if ($configList->value ?? false) {
                                $discountList['max'] = (float) $configList->value;
                            }
                            break;

                        case 'vehicle_and_ic_wise':

                            $configList = AgentDiscountConfiguration::where('setting_name', 'vehicle_and_ic_wise.'.$vehicleType)
                            ->first();

                            if ($configList->value ?? false) {
                                $discount = json_decode($configList->value, true);

                                if (!empty($discount)) {
                                    $discountList['max'] = (float) max($discount);
                                }
                            }
                            break;
                    }
                }
            }
            return ['status' => true, 'discounts' => $discountList];
        }
        return ['status' => false];
    }

    public static function getIcDiscount($enquiryId, $companyAlias, $vehcileType)
    {
        if (config('ENABLE_DISCOUNTING') == 'Y' && self::isUserApplicable($enquiryId)) {

            $applicableICs = AgentDiscountConfiguration::where('setting_name', 'configuration_setting.applicable_ics')->first()->value ?? null;
            
            $applicableICs = explode(',', $applicableICs);
            
            $companyId = MasterCompany::where('company_alias', $companyAlias)->first()->company_id ?? '';

            if (!in_array($companyId, $applicableICs)) {
                return ['status' => false];
            }

            $userNameCriteria = AgentDiscountConfiguration::where('setting_name', 'configuration_setting.applicable_to_user')
            ->first();

            $userCriteria = explode(',', $userNameCriteria->value ?? '');
            $query =  CvAgentMapping::where('user_product_journey_id', $enquiryId)
            ->where(function ($query) use ($userCriteria) {
                foreach ($userCriteria as $key => $name) {
                    if (!empty($name)) {
                        if ($key == 0) {
                            $query->whereRaw('UPPER(agent_name) LIKE "' . strtoupper($name).'%"');
                        } else {
                            $query->orWhereRaw('UPPER(agent_name) LIKE "' . strtoupper($name).'%"');
                        }
                    }
                }
            });

            $agentDetails = $query->first();

            if (empty($agentDetails)) {
                return ['status' => false];
            }

            $maxDiscount = 0;
            $vehicleCategoryList = ['car', 'bike', 'pcv', 'gcv'];

            $discountList = [];

            foreach ($vehicleCategoryList as $value) {
                $discountList[$value] = 0;
            }
            $selectedConfigId = AgentDiscountConfiguration::where('setting_name', 'activation.applicable_configuration')
            ->first()->value ?? null;
            if ($selectedConfigId) {
                $configMethods = AgentMasterDiscount::find($selectedConfigId);

                if ($configMethods->discount_code ?? null) {
                    switch ($configMethods->discount_code) {
                        case 'global_configuration':
                            $maxDiscount = AgentDiscountConfiguration::where('setting_name', 'global_config.global_percentage_for_all')
                            ->first()->value ?? 0;

                            break;
                        case 'vehicle_wise':
                            $maxDiscount = AgentDiscountConfiguration::where('setting_name', 'vehicle_wise.'.$vehcileType)
                            ->first()->value ?? 0;
                            break;
                        case 'vehicle_and_ic_wise':

                            $maxDiscount = 0;
                            $discount = AgentDiscountConfiguration::where('setting_name', 'vehicle_and_ic_wise.'.$vehcileType)
                            ->first();

                            if ($discount->value ?? false) {
                                $discount = json_decode($discount->value, true);
                                $maxDiscount = $discount[$companyId] ?? 0;
                            }
                            break;
                    }
                }
            }

            $selectedDiscount = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
            if (!empty($selectedDiscount->agent_discount ?? null)) {
                $selectedDiscount = $selectedDiscount->agent_discount['selected'] ?? $maxDiscount;
                if ($selectedDiscount > $maxDiscount) {
                    return ['status' => false, 'message' => 'Invalid discount percentage'];
                }
                $maxDiscount = $selectedDiscount;
            }else {
                $maxDiscount = 0;
            }
            return ['status' => true, 'discount' => $maxDiscount];
        }
        return ['status' => false];
    }

    public static function getProductDetails($productList, $enquiryId)
    {
        if (config('ENABLE_DISCOUNTING') == 'Y' && self::isUserApplicable(customDecrypt($enquiryId))) {

            $applicableICs = AgentDiscountConfiguration::where('setting_name', 'configuration_setting.applicable_ics')
            ->first()->value ?? null;

            $applicableICs = explode(',', $applicableICs);

            foreach ($productList as $productKey => $productType) {
                $productList[$productKey] = array_values(array_filter($productType, function($ic) use ($applicableICs) {
                    return in_array($ic['companyId'], $applicableICs);
                }));
            }
        }
        return $productList;
    }


    public static function isUserApplicable($enquiryId)
    {
        $userNameCriteria = AgentDiscountConfiguration::where('setting_name', 'configuration_setting.applicable_to_user')
        ->first();
        $userCriteria = explode(',', $userNameCriteria->value ?? '');
        $query =  CvAgentMapping::select('agent_name')
        ->where('user_product_journey_id', $enquiryId)
            ->where(function ($query) use ($userCriteria) {
                foreach ($userCriteria as $key => $name) {
                    if (!empty($name)) {
                        if ($key == 0) {
                            $query->whereRaw('UPPER(agent_name) LIKE "' . strtoupper($name) . '%"');
                        } else {
                            $query->orWhereRaw('UPPER(agent_name) LIKE "' . strtoupper($name) . '%"');
                        }
                    }
                }
            });

        $agentDetails = $query->first();

        return !empty($agentDetails);
    }
}
            