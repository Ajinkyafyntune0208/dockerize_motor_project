<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommonConfigurations;
use App\Models\MasterCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentGatewayConfigurationController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->can('configurator.payment_gateway')) {
            return abort(403, 'Unauthorized action.');
        }

        $gatewayList = self::gatewayList();
        
        if ($request->method() == 'GET') {
            return view('admin.payment-gateway-config.setting', compact('gatewayList'));
        } else {

            $configMethods = self::configMethods();

            $validator = Validator::make($request->all(), [
                'paymentGateway' => 'required|in:' . implode(',', array_keys($gatewayList)),
                'configType' => 'required|in:'.implode(',', array_keys($configMethods))
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withInput()->with('error', $validator->errors()->first());
            }

            CommonConfigurations::updateOrCreate([
                'key' => 'paymentGateway.'.$request->paymentGateway.'.configType'
            ], [
                'label' => $request->paymentGateway. ' payment gateway config type',
                'value' => $request->configType
            ]);

            \Illuminate\Support\Facades\Artisan::call('optimize:clear');

            return redirect()->back()->with('success', 'Config setting updated successfully!..');
        }
    }

    public function getConfigType(Request $request)
    {
        $gatewayList = self::gatewayList();

        $configMethods = self::configMethods();

        $validator = Validator::make($request->all(), [
            'paymentGateway' => 'required|in:' . implode(',', array_keys($gatewayList))
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'configMethods' => $configMethods,
                'selectedConfig' => getCommonConfig('paymentGateway.'.$request->paymentGateway.'.configType', null)
            ]
        ]);
    }

    public function getGatewayFields(Request $request)
    {
        $gatewayList = self::gatewayList();

        $validator = Validator::make($request->all(), [
            'paymentGateway' => 'required|in:' . implode(',', array_keys($gatewayList)),
            'type' => 'required|in:globalConfig,icWiseConfig',
            'insuranceCompany' => 'required_if:type,icWiseConfig'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()
            ]);
        }

        $fields = self::fields();

        $fields = $fields[$request->paymentGateway];
        foreach($fields as $key => $value) {
            $keyName = 'paymentGateway.'.$request->paymentGateway.'.';

            if ($request->type == 'icWiseConfig') {
                $keyName.=$request->insuranceCompany.'.';
            }
            $keyName.= $value['key'];

            $fields[$key]['value'] = getCommonConfig($keyName, null);
        }

        return response()->json([
            'status' => true,
            'data' => $fields
        ]);
    }

    public function globalConfig(Request $request)
    {
        $gatewayList = self::gatewayList();
        
        if ($request->method() == 'GET') {
            return view('admin.payment-gateway-config.global-config', compact('gatewayList'));
        } else {
            $validator = Validator::make($request->all(), [
                'paymentGateway' => 'required|in:' . implode(',', array_keys($gatewayList))
            ]);
    
            if ($validator->fails()) {
                return redirect()->back()->withInput()->with('error', $validator->errors()->first());
            }

            $fields = self::fields();

            $fields = $fields[$request->paymentGateway];

            foreach ($fields as $value) {

                CommonConfigurations::updateOrCreate([
                    'key' => 'paymentGateway.'.$request->paymentGateway.'.'.$value['key']
                ], [
                    'label' => $request->paymentGateway. ' payment gateway '.$value['label'],
                    'value' => $request->{$value['key']}
                ]);
            }

            \Illuminate\Support\Facades\Artisan::call('optimize:clear');
            return redirect()->back()->with('success', 'Config setting updated successfully!..');
            
        }
    }

    public function icWiseConfig(Request $request)
    {
        $gatewayList = self::gatewayList();

        $ics = MasterCompany::select('company_alias')
        ->whereNotNull('company_alias')
        ->pluck('company_alias')
        ->toArray();

        if ($request->method() == 'GET') {
            return view('admin.payment-gateway-config.ic-wise-config', compact('gatewayList', 'ics'));
        } else {
            $validator = Validator::make($request->all(), [
                'paymentGateway' => 'required|in:' . implode(',', array_keys($gatewayList)),
                'insuranceCompany' => 'required:in'.implode(',', $ics)
            ]);
    
            if ($validator->fails()) {
                return redirect()->back()->withInput()->with('error', $validator->errors()->first());
            }

            $fields = self::fields();

            $fields = $fields[$request->paymentGateway];

            foreach ($fields as $value) {

                CommonConfigurations::updateOrCreate([
                    'key' => 'paymentGateway.'.$request->paymentGateway.'.'.$request->insuranceCompany.'.'.$value['key']
                ], [
                    'label' => $request->paymentGateway. ' payment gateway '.$value['label'] . ' for '.$request->insuranceCompany,
                    'value' => $request->{$value['key']}
                ]);
            }
            \Illuminate\Support\Facades\Artisan::call('optimize:clear');

            return redirect()->back()->with('success', 'Config setting updated successfully!..');
    
        }
    }

    public static function fields()
    {
        return [
            'paytm' => [
                [
                    'key' => 'mid',
                    'label' => 'MID'
                ],
                [
                    'key' => 'merchantKey',
                    'label' => 'Merchant Key',
                ],
                [
                    'key' => 'websiteName',
                    'label' => 'Website Name',
                ]
                ],
            'onepay' => [
                [
                    'key' => 'merchantId',
                    'label' => 'Merchant Id'
                ],
                [
                    'key' => 'apiKey',
                    'label' => 'Api Key',
                ],
                [
                    'key' => 'encryptionKey',
                    'label' => 'Encryption Key',
                ]
            ]
        ];
    }

    public static function gatewayList()
    {
        return [
            'paytm' => 'Paytm',
            'onepay' => 'One pay'
        ];
    }

    public static function configMethods()
    {
        return [
            'global' => "Global Configuration",
            'ic' => "IC wise Configuration",
        ];
    }
}
