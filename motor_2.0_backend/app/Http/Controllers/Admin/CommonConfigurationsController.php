<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommonConfigurations;
use App\Models\WebserviceRequestResponseDataOptionList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommonConfigurationsController extends Controller
{
    public function index()
    {
        $allData = CommonConfigurations::select('label', 'key', 'value')->get()->pluck('value', 'key');
        $companies = WebserviceRequestResponseDataOptionList::select('company')->distinct()->orderBy('company')->get();
        $existingRecord = DB::table('config_settings')
        ->where('key', 'constants.motorConstant.DASHBOARD_DATA_PUSH_ENABLED')
        ->first();
        $keys = [
            'MONGO_DB_AUTHENTICATION_DATABASE',
            'MONGO_DB_CA_FILE_PATH',
            'MONGO_DB_DATABASE',
            'MONGO_DB_HOST',
            'MONGO_DB_PASSWORD',
            'MONGO_DB_PORT',
            'MONGO_DB_RETRY_WRITES',
            'MONGO_DB_SSL_CONNECTION',
            'MONGO_DB_USERNAME'
        ];
        $keyValuePairs = DB::table('config_settings')->whereIn('key', $keys)
        ->pluck('value', 'key')
        ->toArray();
        if ($existingRecord) {
            $existingRecord = $existingRecord->value;
        }
        return view('common_configurations.index', compact('allData', 'companies', 'existingRecord','keyValuePairs'));
    }
    public function save(Request $request)
    {
        try {
            if (in_array($request->formType, ['renewalConfig', 'loadingConfig'])) {
                $data = $request->data;
                foreach ($request->data as $k => $v) {
                    if (($v['value'] ?? 'N') != 'on') {
                        $v['value'] = 'N';
                    } else {
                        $v['value'] = 'Y';
                    }
                    $data[$k] = $v;
                }
                $request->data = $data;
            }

            foreach ($request->data as $k => $v) {
                $userData = [
                    'label' => trim($v['label']),
                    'key' => trim($v['key']),
                    'value' => trim($v['value']),
                ];
                $user = CommonConfigurations::updateOrCreate(
                    ['key' => trim($v['key'])], // search criteria
                    $userData // new or updated data
                );
                if (!$user) {
                    return back()->withErrors(['message' => 'Something went wrong']);
                }
                
            }
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            setCommonConfigInCache();
                return redirect()->route('admin.common-config')->with([
                    'status' => 'Saved Successfully..!',
                    'formType' => $request->formType,
                    'class' => 'success',
                ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something went wrong ' . $e->getMessage() . '...!',
                'formType' => $request->formType,
                'class' => 'danger',
            ]);
        }
    }
    function saveMongoConfig(Request $request)
    {
        if (isset($request->is_configured) && $request->is_configured === 'on') {
            $data = $request->only('mongo_db_host', 'mongo_db_port', 'mongo_db_database', 'mongo_db_username', 'mongo_db_password', 'mongo_db_authentication_database', 'mongo_db_retry_writes', 'mongo_db_ssl_connection', 'mongo_db_ca_file_path');
            foreach ($data as $key => $value) {
                $existingRecord = DB::table('config_settings')
                ->where('key', strtoupper($key))
                ->first();

                if ($existingRecord) {
                    $updated = DB::table('config_settings')
                    ->where('key', strtoupper($key))
                    ->update(['value' => $value, 'updated_at'=>now()]);
                } else {
                    $inserted = DB::table('config_settings')->insert([
                        'label' => strtoupper($key),
                        'key' => strtoupper($key),
                        'value' => $value,
                        'environment' => 'local'
                    ]);
                }
            }
            $existingRecord = DB::table('config_settings')
            ->where('key', 'constants.motorConstant.DASHBOARD_DATA_PUSH_ENABLED')
            ->first();

            if ($existingRecord) {
                $updated = DB::table('config_settings')
                ->where('key', 'constants.motorConstant.DASHBOARD_DATA_PUSH_ENABLED')
                ->update(['value' => 'Y','updated_at'=>now()]);
            } else {
                $inserted = DB::table('config_settings')->insert([
                    'label' => 'constants.motorConstant.DASHBOARD_DATA_PUSH_ENABLED',
                    'key' => 'constants.motorConstant.DASHBOARD_DATA_PUSH_ENABLED',
                    'value' => 'Y',
                    'environment' => 'local'
                ]);
            }
            return redirect()->back()->with(
                [
                    'mongo-config-msg' => 'Status & Configuration Updated Successsfully',
                    'class' => 'success',
                    'focus' => true
                ]
            );
        } else {
            $existingRecord = DB::table('config_settings')
            ->where('key', 'constants.motorConstant.DASHBOARD_DATA_PUSH_ENABLED')
            ->first();

            if ($existingRecord) {
                $updated = DB::table('config_settings')
                ->where('key', 'constants.motorConstant.DASHBOARD_DATA_PUSH_ENABLED')
                ->update(['value' => 'N','updated_at'=>now()]);
            } else {
                $inserted = DB::table('config_settings')->insert([
                    'label' => 'constants.motorConstant.DASHBOARD_DATA_PUSH_ENABLED',
                    'key' => 'constants.motorConstant.DASHBOARD_DATA_PUSH_ENABLED',
                    'value' => 'N',
                    'environment' => 'local'
                ]);
            }
            return redirect()->back()->with(
                [
                    'mongo-config-msg' => 'Status Updated Successsfully',
                    'class' => 'success',
                    'focus' => true
                ]
            );
        }
    }
}
