<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentDiscountConfiguration;
use App\Models\AgentMasterDiscount;
use App\Models\MasterPolicy;
use App\Models\MasterProduct;
use App\Models\userActivityLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Svg\Tag\Rect;

class DiscountConfigurationController extends Controller
{
    public function configSetting(Request $request)
    {
        if (!auth()->user()->can('discount.config')) {
            return abort(403, 'Unauthorized action.');
        }
        if ($request->method() == 'GET') {

            $configs = AgentDiscountConfiguration::whereIn('setting_name', [
                'configuration_setting.applicable_to_user',
                'configuration_setting.applicable_ics'
            ])->get();
            $userCriteria = '';

            $icList = MasterPolicy::where('master_policy.status', 'Active')
            ->join('master_company as mc', 'mc.company_id', '=', 'master_policy.insurance_company_id')
            ->groupBy('master_policy.insurance_company_id')
            ->select('mc.company_name', 'mc.company_id')
            ->get();

            $selectedIcs = '';


            foreach ($configs as $c) {
                if ($c->setting_name == 'configuration_setting.applicable_to_user') {
                    $userCriteria = $c->value;
                }

                if ($c->setting_name == 'configuration_setting.applicable_ics') {
                    $selectedIcs = $c->value;
                }
            }

            $selectedIcs = explode(',', $selectedIcs);

            $icList = $icList->map(function ($insuranceCompany) use ($selectedIcs) {
                if (in_array($insuranceCompany->company_id, $selectedIcs)) {
                    $insuranceCompany->is_selected = true;
                } else {
                    $insuranceCompany->is_selected = false;
                }
                return $insuranceCompany;
            });
            return view('discount-configuration.config-setting', compact('userCriteria', 'icList'));
        } else {
            $validator = Validator::make($request->all(), [
                'userCriteria' => 'required',
                'applicableIcs' => 'required|array'
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withInput()->with('error', $validator->errors()->first());
            }

            AgentDiscountConfiguration::updateorCreate([
                'setting_name' => 'configuration_setting.applicable_to_user'
            ],[
                'value' => $request->userCriteria
            ]);

            AgentDiscountConfiguration::updateorCreate([
                'setting_name' => 'configuration_setting.applicable_ics'
            ],[
                'value' => implode(',', $request->applicableIcs)
            ]);

            $config = AgentDiscountConfiguration::whereIn('setting_name', [
                'vehicle_and_ic_wise.bike',
                'vehicle_and_ic_wise.car',
                'vehicle_and_ic_wise.pcv',
                'vehicle_and_ic_wise.gcv'
            ])
            ->get();

            foreach($config as $c) {
                $icConfig = json_decode($c->value, true);
                $removalIcs = array_diff(array_keys($icConfig), $request->applicableIcs);
                if(!empty($removalIcs)) {
                    foreach ($removalIcs as $value) {
                        unset($icConfig[$value]);
                    }
                    AgentDiscountConfiguration::where('setting_name', $c->setting_name)
                    ->update(['value' => json_encode($icConfig)]);
                }
            }

            return redirect()->back()->with('success', 'Config setting updated successfully!..');
        }
    }
    
    public function globalConfig(Request $request)
    {
        if (!auth()->user()->can('discount.config')) {
            return abort(403, 'Unauthorized action.');
        }
        if ($request->method() == 'GET') {
            $configs = AgentDiscountConfiguration::where('setting_name', 'global_config.global_percentage_for_all')
            ->first();

            $globalConfig = $configs->value ?? '';
            return view('discount-configuration.global-config', compact('globalConfig'));
        } else {
            $validator = Validator::make($request->all(), [
                'globalConfig' => 'required|numeric|between:0.1,100'
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withInput()->with('error', $validator->errors()->first());
            }

            AgentDiscountConfiguration::updateorCreate([
                'setting_name' => 'global_config.global_percentage_for_all'
            ],[
                'value' => $request->globalConfig
            ]);

            return redirect()->back()->with('success', 'Global confg updated successfully!..');
        }
    }
    
    public function vehicleConfig(Request $request)
    {
        if (!auth()->user()->can('discount.config')) {
            return abort(403, 'Unauthorized action.');
        }
        if ($request->method() == 'GET') {
            $config = AgentDiscountConfiguration::whereIn('setting_name', [
                'vehicle_wise.bike',
                'vehicle_wise.car',
                'vehicle_wise.pcv',
                'vehicle_wise.gcv'
            ])
            ->get();
            $vehicleDiscount = [
                'car' => '',
                'bike' => '',
                'pcv' => '',
                'gcv' => '',
            ];
            foreach($config as $c) {
                switch($c->setting_name) {
                    case 'vehicle_wise.bike' : $vehicleDiscount['bike'] = $c->value;break;
                    case 'vehicle_wise.car' : $vehicleDiscount['car'] = $c->value;break;
                    case 'vehicle_wise.pcv' : $vehicleDiscount['pcv'] = $c->value;break;
                    case 'vehicle_wise.gcv' : $vehicleDiscount['gcv'] = $c->value;break;
                }
            }
            return view('discount-configuration.vehicle-config', compact('vehicleDiscount'));
        } else {
            $validator = Validator::make($request->all(), [
                'bikeDiscount' => 'required|numeric|between:0.1,100',
                'carDiscount' => 'required|numeric|between:0.1,100',
                'pcvDiscount' => 'required|numeric|between:0.1,100',
                'gcvDiscount' => 'required|numeric|between:0.1,100',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withInput()->with('error', $validator->errors()->first());
            }

            AgentDiscountConfiguration::updateorCreate([
                'setting_name' => 'vehicle_wise.bike'
            ],[
                'value' => $request->bikeDiscount
            ]);

            AgentDiscountConfiguration::updateorCreate([
                'setting_name' => 'vehicle_wise.car'
            ],[
                'value' => $request->carDiscount
            ]);

            AgentDiscountConfiguration::updateorCreate([
                'setting_name' => 'vehicle_wise.pcv'
            ],[
                'value' => $request->pcvDiscount
            ]);

            AgentDiscountConfiguration::updateorCreate([
                'setting_name' => 'vehicle_wise.gcv'
            ],[
                'value' => $request->gcvDiscount
            ]);
            return redirect()->back()->with('success', 'Vehicle config updated successfully!..');
        }
    }

    public function icConfig(Request $request)
    {
        if (!auth()->user()->can('discount.config')) {
            return abort(403, 'Unauthorized action.');
        }
        if ($request->method() == 'GET') {
            $applicableIcs = AgentDiscountConfiguration::where('setting_name', 'configuration_setting.applicable_ics')
            ->first();

            if ($applicableIcs->value ??  false) {
                $applicableIcs = explode(',', $applicableIcs->value);

                $applicableIcs = MasterPolicy::where('master_policy.status', 'Active')
                ->join('master_company as mc', 'mc.company_id', '=', 'master_policy.insurance_company_id')
                ->whereIn('mc.company_id', $applicableIcs)
                ->groupBy('master_policy.insurance_company_id')
                ->select('mc.company_name', 'mc.company_id')
                ->get();
            } else {
                $applicableIcs = [];
            }

            $discounts = [
                'car' =>'',
                'bike' =>'',
                'gcv' =>'',
                'cv' =>''
            ];

            $config = AgentDiscountConfiguration::whereIn('setting_name', [
                'vehicle_and_ic_wise.bike',
                'vehicle_and_ic_wise.car',
                'vehicle_and_ic_wise.pcv',
                'vehicle_and_ic_wise.gcv'
            ])
            ->get();
            foreach($config as $c) {
                switch($c->setting_name) {
                    case 'vehicle_and_ic_wise.bike' : $discounts['bike'] = json_decode($c->value, true);break;
                    case 'vehicle_and_ic_wise.car' : $discounts['car'] = json_decode($c->value, true);break;
                    case 'vehicle_and_ic_wise.pcv' : $discounts['pcv'] = json_decode($c->value, true);break;
                    case 'vehicle_and_ic_wise.gcv' : $discounts['gcv'] = json_decode($c->value, true);break;
                }
            }
            return view('discount-configuration.ic-config', compact('applicableIcs','discounts'));
        } else {
            $validator = Validator::make(
                $request->all(),
                [
                    'type' => 'required|in:bike,car,gcv,pcv',
                    $request->type . 'Discount' => 'required|array',
                    $request->type . 'Discount.*' => 'required|numeric|between:0.1, 100',
                ],
                [
                    $request->type . 'Discount.*.required' => 'The discount value is required.',
                    $request->type . 'Discount.*.numeric' => 'The discount must be numeric.',
                    $request->type . 'Discount.*.between' => 'The discount must be between 0.1 and 100.',
                ]
            );

            if ($validator->fails()) {
                return redirect()->back()->withInput()->with('error', $validator->errors()->first());
            }
            $type = $request->type;
            $icDiscounts =  $request->get($request->type.'Discount');

            AgentDiscountConfiguration::updateorCreate([
                'setting_name' => 'vehicle_and_ic_wise.'.$type
            ],[
                'value' => json_encode($icDiscounts)
            ]);
            return redirect()->back()->with('success', 'Ic wise config updated successfully!..');
        }
    }

    public function activeConfig(Request $request)
    {
        if (!auth()->user()->can('discount.config')) {
            return abort(403, 'Unauthorized action.');
        }
        if ($request->method() == 'GET') {
            $configMethods = AgentMasterDiscount::select('discount_name', 'id')
            ->get();
            $selectedConfigId = AgentDiscountConfiguration::where('setting_name', 'activation.applicable_configuration')
            ->first()->value ?? '';
            return view('discount-configuration.active-config', compact('configMethods', 'selectedConfigId'));
        } else {

            $configMethods = AgentMasterDiscount::get()->pluck('id')->toArray();
            $validator = Validator::make($request->all(), [
                'configType' => 'required|in:'.implode(',', $configMethods)
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withInput()->with('error', $validator->errors()->first());
            }

            AgentDiscountConfiguration::updateorCreate([
                'setting_name' => 'activation.applicable_configuration'
            ],[
                'value' => $request->configType
            ]);
            return redirect()->back()->with('success', 'Vehicle config updated successfully!..');
        }
    }

    public function validateIcs(Request $request)
    {
        if (!auth()->user()->can('discount.config')) {
            return abort(403, 'Unauthorized action.');
        }
        $config = AgentDiscountConfiguration::where('setting_name','configuration_setting.applicable_ics')->first();
        $applicableIcs = [];
        if (isset($config->value)) {
            $applicableIcs = explode(',', $config->value);
        }
        $icList = explode(',', $request->ic ?? '');

        return response()->json([
            'status' => true,
            'is_confirm' => count(array_diff($applicableIcs, $icList)) > 0
        ]);
    }


    public function activityLogs(Request $request)
    {
        if (!auth()->user()->can('discount.config.activity-logs')) {
            return abort(403, 'Unauthorized action.');
        }
        $types = ['Agent Discount Configuration'];

        $activities = [];
        if ($request->has('type') && $request->has('from') && $request->has('to')) {
            $activities = userActivityLog::orderBy('id', 'DESC');
            if (!auth()->user()->hasRole('Admin')) {
                $activities = $activities->where('user_id', auth()->user()->id);
            }
            $activities = $activities->whereBetween('created_at', [
                Carbon::parse($request->from)->startOfDay(), Carbon::parse($request->to)->endOfDay()
            ])
                ->whereIn('service_type', $request->type)
                ->paginate($request->paginate);
        }
        return view('discount-configuration.activity-logs', compact('activities', 'types'));
    }

    public function hdfcPaymentStatus(Request $request) {

        try {
            $header = [
                'SOURCE' => $request->source,
                'CHANNEL_ID' => $request->channelId,
                'CREDENTIAL' => $request->credential,
                'PRODUCT_CODE' => $request->productCode,
                'TRANSACTIONID' => $request->transactionId
            ];
            $response = Http::withHeaders($header)->get($request->tokenUrl);
            $tokenResponse = $response->json();
    
            if (empty($tokenResponse)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Token service failure',
                    'data' => $tokenResponse ?? $response ?? null
                ]);
            }
            $token = $tokenResponse['Authentication']['Token'] ?? null;
    
            if (empty($token)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Token not found',
                    'data' => $response
                ]);
            }
            $header ['TOKEN'] = $token;
            $body = $request->body;
            $url = $request->statusUrl;
            $response = Http::withHeaders($header)->post($url, $body);
            $paymentResponse = $response->json();
    
              return response()->json([
                'status' => true,
                'response' => $paymentResponse ?? $response ?? null
              ]);
        } catch (\Throwable $th) {
            info($th);
            return response()->json([
                'status' => false,
                'error' => $th->getMessage()
            ]);
        }
    }
}
