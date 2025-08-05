<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfigSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CommisionConfiguratorController extends Controller
{
    public $ruleTypes = [
        'BROKEX',
        'API'
    ];

    public $rewardTypes = [
        'AMOUNT',
        'POINTS'
    ];

    public function index(Request $request)
    {
        if (empty($request->ruleType)) {
            $ruleType = config('BROKER_COMMISSION_RULE_TYPE') == 'API' ? 'API' : 'BROKEX';
            return redirect()->route('admin.ic-config.commision-configurator.index', ['ruleType' => $ruleType]);
        }
        $configs = [
            'enableCommission' => config('ENABLE_BROKERAGE_COMMISSION'),
            'ruleType' => config('BROKER_COMMISSION_RULE_TYPE'),
            'payInAllowed' => config('BROKER_COMMISSION_PAYIN_ALLOWED'),
            'rewardType' => config('BROKER_COMMISSION_REWARD_TYPE'),
            'quoteShareAllowed' => config('DISABLE_COMMISSION_ON_QUOTE_SHARE'),

            'brokerId' => config('BROKER_ID_FOR_BROKERAGE_COMMISSION'),
            'retrospectiveSchedular' => config('ENABLE_COMMISSION_RETROSPECTIVE_SCHEDULAR'),
            'databaseDriver' => config('database.connections.brocore.driver'),
            'databaseHost' => config('database.connections.brocore.host'),
            'databasePort' => config('database.connections.brocore.port'),
            'databaseUserName' => config('database.connections.brocore.username'),
            'databasePassword' => config('database.connections.brocore.password'),
            'databaseName' => config('database.connections.brocore.database'),

            'isB2cAllowed' => config('DISABLE_B2C_IN_COMMISSION_CALCULATION'),
        ];

        return view('admin_lte.commision-configurator.index', [
            'rewardTypes' => $this->rewardTypes,
            'configs' => $configs
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'enableCommission' => 'required|in:Y,N',
            'ruleType' => 'required|in:'.implode(',', $this->ruleTypes),
            'payInAllowed' => 'required|in:Y,N',
            'rewardType' => 'required|in:'.implode(',', $this->rewardTypes),
            'quoteShare' => 'required|in:Y,N',
        ];

        if ($request->ruleType == 'API') {
            $rules = array_merge($rules, [
                'isB2cAllowed' => 'required|in:Y,N'
            ]);
        } else {
            $rules = array_merge($rules, [
                'brokerId' => 'required',
                'databaseDriver' => 'required',
                'databaseHost' => 'required',
                'databasePort' => 'required',
                'databaseUserName' => 'required',
                'databasePassword' => 'required',
                'databaseName' => 'required',
                'retrospectiveSchedular' => 'required|in:Y,N',
            ]);
        }
        $validator  = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return redirect()->back()->withInput()->with('error', $validator->errors()->first());
        }

        $updateConfig = [
            [
                'label' => 'ENABLE_BROKERAGE_COMMISSION',
                'key' => 'ENABLE_BROKERAGE_COMMISSION',
                'value' => $request->enableCommission,
            ],[
                'label' => 'BROKER_COMMISSION_PAYIN_ALLOWED',
                'key' => 'BROKER_COMMISSION_PAYIN_ALLOWED',
                'value' => $request->payInAllowed
            ],[
                'label' => 'BROKER_COMMISSION_REWARD_TYPE',
                'key' => 'BROKER_COMMISSION_REWARD_TYPE',
                'value' => $request->rewardType
            ],
            [
                'label' => 'DISABLE_COMMISSION_ON_QUOTE_SHARE',
                'key' => 'DISABLE_COMMISSION_ON_QUOTE_SHARE',
                'value' => $request->quoteShare
            ],

        ];

        if ($request->ruleType == 'API') {
            $updateConfig[] = [
                'label' => 'DISABLE_B2C_IN_COMMISSION_CALCULATION',
                'key' => 'DISABLE_B2C_IN_COMMISSION_CALCULATION',
                'value' => $request->isB2cAllowed
            ];
        } else {
            $updateConfig = array_merge($updateConfig, [
                [
                    'label' => 'BROKER_ID_FOR_BROKERAGE_COMMISSION',
                    'key' => 'BROKER_ID_FOR_BROKERAGE_COMMISSION',
                    'value' => $request->brokerId,
                ],
                [
                    'label' => 'ENABLE_COMMISSION_RETROSPECTIVE_SCHEDULAR',
                    'key' => 'ENABLE_COMMISSION_RETROSPECTIVE_SCHEDULAR',
                    'value' => $request->retrospectiveSchedular,
                ],
                [
                    'label' => 'database_connections_brocore_driver',
                    'key' => 'database.connections.brocore.driver',
                    'value' => $request->databaseDriver,
                ],
                [
                    'label' => 'database_connections_brocore_host',
                    'key' => 'database.connections.brocore.host',
                    'value' => $request->databaseHost,
                ],
                [
                    'label' => 'database_connections_brocore_port',
                    'key' => 'database.connections.brocore.port',
                    'value' => $request->databasePort,
                ],
                [
                    'label' => 'database_connections_brocore_username',
                    'key' => 'database.connections.brocore.username',
                    'value' => $request->databaseUserName,
                ],
                [
                    'label' => 'database_connections_brocore_password',
                    'key' => 'database.connections.brocore.password',
                    'value' => $request->databasePassword,
                ],
                [
                    'label' => 'database_connections_brocore_database',
                    'key' => 'database.connections.brocore.database',
                    'value' => $request->databaseName,
                ],
            ]);
        }

        foreach ($updateConfig as $item) {
            ConfigSetting::updateOrcreate([
                'key' => $item['key']
            ], [
                'value' => $item['value'],
                'label' => $item['label']
            ]);
        }

        \Illuminate\Support\Facades\Artisan::call('optimize:clear');

        return redirect()->back()->with('success', 'Commission data has been saved successfully');
    }
}
