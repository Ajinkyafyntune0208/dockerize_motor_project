<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Schema\Blueprint;

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 1800);

class MmvController extends Controller
{
    public function getMmvData()
    {
        $env = config('app.env');
        if ($env == 'local') {
            $env_folder = 'uat';
            $mmv_link = config('constants.mmv.MMV_UAT_API_URL_LIST');
        } else if ($env == 'test') {
            $env_folder = 'production';
            $mmv_link = config('constants.mmv.MMV_PROD_API_URL_LIST');
        } else if ($env == 'live') {
            $env_folder = 'production';
            $mmv_link = config('constants.mmv.MMV_PROD_API_URL_LIST');
        }

        $all_links = httpRequestNormal($mmv_link, 'GET', [], [], [], [], false)['response'];
        $erros = [];
        if ($all_links) {
            foreach ($all_links as $link) {
                $mmv_data = httpRequestNormal($link, 'GET', [], [], [], [], false)['response'];
                if ($mmv_data) {
                    $link_array = explode('/', $link);
                    $file_name  = end($link_array) . '.json';
                    $mmv_path = 'mmv_masters/' . $env_folder . '/' . $file_name;
                    $delete_path = 'mmv_masters/' . $env_folder . '/' . $file_name;
                    Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->delete($delete_path);
                    if (is_array($mmv_data) && !empty($mmv_data[end($link_array)])) {
                        Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->put($mmv_path, json_encode($mmv_data[end($link_array)]));
                    } else {
                        $erros[] = [$mmv_path => $mmv_data];
                    }
                }
            }
        }
        return response()->json([
            "status" => true,
            "msg" => "Mmv Data Sync Up Successfully...!",
            "data" => $erros
        ]);
    }
    public function mmv_sync(Request $request)
    {
        $env = config('app.env');
        if ($env == 'local') {
            $env_folder = 'uat';
            $mmv_link = config('constants.mmv.MMV_UAT_API_URL_LIST');
        } else if ($env == 'test') {
            $env_folder = 'production';
            $mmv_link = config('constants.mmv.MMV_PROD_API_URL_LIST');
        } else if ($env == 'live') {
            $env_folder = 'production';
            $mmv_link = config('constants.mmv.MMV_PROD_API_URL_LIST');
        }

        $all_links = Http::get($mmv_link)->json();
        if ($request->slug === 'all') {
            $mmv_names = [];
            $comment='';
            foreach ($all_links as $value) {
                $link_array = explode('/', $value);
                $name =  end($link_array);
                $disk = config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public';
                if (Storage::disk($disk)->exists('mmv_masters/' . $env_folder . '/' . $name . '.json')) {
                    $last_modified = Storage::disk($disk)->lastModified('mmv_masters/' . $env_folder . '/' . $name . '.json');
                    $last_modified = Carbon::createFromTimestamp($last_modified);
                    $comment = $last_modified->diffForHumans();
                    $last_modified = $last_modified->toDateTimeString();
                } else {
                    $last_modified = null;
                }
                $single_record = [
                    'name' => $name,
                    'comment' => $last_modified ? $last_modified : 'N/A',
                    'lastModified' => $comment ? $comment : null,
                    'file_exists' => $last_modified ? $last_modified : null,
                    'status' => 'N/A',
                ];
                array_push($mmv_names, $single_record);
            }
            return response()->json([
                "status" => true,
                "msg" => "All Record Found",
                "data" => $mmv_names
            ]);
        }
        $match = false;
        if ($all_links) {
            $errors = [];
            foreach ($all_links as $index => $link) {
                $link_array = explode('/', $link);
                if (end($link_array) === $request->slug) {
                    $match = true;
                    $mmv_data = Http::get($link)->json();
                    if ($mmv_data) {
                        $file_name  = end($link_array) . '.json';
                        $mmv_path = 'mmv_masters/' . $env_folder . '/' . $file_name;
                        $delete_path = 'mmv_masters/' . $env_folder . '/' . $file_name;
                        Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->delete($delete_path);
                        if (is_array($mmv_data) && !empty($mmv_data[end($link_array)])) {
                            Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->put($mmv_path, json_encode($mmv_data[end($link_array)]));
                        } else {
                            $errors[] = [$mmv_path => $mmv_data];
                        }
                        return response()->json([
                            "status" => true,
                            "msg" => "Mmv Data for $request->slug Sync Up Successfully...!",
                            "data" => $errors
                        ]);
                    }
                } else {
                    continue;
                }
            }
        }
        if ($match == false) {
            return response()->json([
                "status" => false,
                "msg" => "Incorrect Master",
            ]);
        }
    }

    public function getAbiblMgMapping()
    {
        set_time_limit(0);
        $env = config('app.env');
        if ($env == 'local') {
            $env_folder = 'uat';
        } else if ($env == 'test') {
            $env_folder = 'production';
        } else if ($env == 'live') {
            $env_folder = 'production';
        }
        $link = 'https://mmv.fynity.in/admin/mmv/get_data/abibl_mg_mapping';
        $data = httpRequestNormal($link, 'GET', [], [], [], [], false)['response']['abibl_mg_mapping'];
        if (!empty($data)) {
            if (Schema::hasTable('abibl_vehicle_mapping')) {
                DB::table('abibl_vehicle_mapping')->truncate();
                foreach ($data as $key => $value) {
                    $data = [
                        'variant_code'  => $value['VariantCode'],
                        'manf'          => $value['MANUF'],
                        'model_name'    => $value['MODEL_NAME'],
                        'variant_name'  => $value['VariantName'],
                        'cc'            => $value['CC'],
                        'sc'            => $value['SC'],
                        'fuel_type'     => $value['FuelType'],
                        'FTS_CODE'      => $value['FTS_CODE']
                    ];
                    DB::table('abibl_vehicle_mapping')->insert($data);
                }
                $msg = 'Date Inserted';
            } else {
                $msg =  "Table not exits";
            }

            return $msg;
        }
    }

    public function getRtoData(Request $request)
    {
        set_time_limit(0);
        $env = config('app.env');
        if ($env == 'local') {
            $env_folder = 'uat';
            $rto_link = config('constants.mmv.RTO_UAT_API_URL_LIST');
        } else if ($env == 'test') {
            $env_folder = 'production';
            $rto_link = config('constants.mmv.RTO_PROD_API_URL_LIST');
        } else if ($env == 'live') {
            $env_folder = 'production';
            $rto_link = config('constants.mmv.RTO_PROD_API_URL_LIST');
        }

        $all_links = httpRequestNormal($rto_link, 'GET', [], [], [], [], false)['response'];

        if ($all_links) {
            foreach ($all_links as $link) {
                $rto_data = httpRequestNormal($link, 'GET', [], [], [], [], false)['response'];
                if ($rto_data) {
                    $link_array = explode('/', $link);
                    $table_name = end($link_array);
                    $fields = collect($rto_data[$table_name][0])->keys()->toArray();
                    if (Schema::hasTable($table_name))
                        Schema::dropIfExists($table_name);

                    Schema::create($table_name, function (Blueprint $table) use ($fields, $table_name) {
                        foreach ($fields as $key => $value) {
                            $table->string($value)->nullable();
                        }
                    });
                    $rto_data_insert = collect($rto_data[$table_name])->chunk(1000);
                    foreach ($rto_data_insert as $key => $value) {
                        DB::table($table_name)->insert($value->toArray());
                    }
                }
            }
        }
        return 'RTO Data Sync Up Success....!';
    }

    public function syncRto(Request $request)
    {
        set_time_limit(0);
        $env = config('app.env');
        if ($env == 'local') {
            $rto_link = config('constants.mmv.RTO_UAT_API_URL_LIST');
        } else if ($env == 'test') {
            $rto_link = config('constants.mmv.RTO_PROD_API_URL_LIST');
        } else if ($env == 'live') {
            $rto_link = config('constants.mmv.RTO_PROD_API_URL_LIST');
        }

        $all_links = Http::get($rto_link)->json();

        if ($all_links) {
            if ($request->slug === 'all') {
                $table_names = [];
                foreach ($all_links as $value) {
                    $link_array = explode('/', $value);
                    $table_name =  end($link_array);
                    $defaultConnection = config('database.default');
                    $dbName = config('database.connections.' . $defaultConnection)['database'] ?? '';
                    $comments = DB::select("SELECT TABLE_NAME as table_name, TABLE_COMMENT as comment FROM INFORMATION_SCHEMA.TABLES WHERE table_schema = '" . $dbName . "'");
                    $table_comment = '';
                    foreach ($comments as $comment) {
                        if ($comment->table_name === $table_name) {
                            $table_comment = $comment->comment;
                        }
                    }
                    if ($table_comment !== '') {
                        $date = str_replace('RTO master - ', '', $table_comment);
                        $date = str_replace('MDM master - ', '', $date);
                        $last_sync = Carbon::createFromDate($date)->diffForHumans();
                    } else {
                        $last_sync = '';
                    }
                    $single_record = [
                        'name' => $table_name,
                        'lastSync' => $last_sync,
                        'comment' => $table_comment,
                        'status' => 'N/A',
                    ];
                    array_push($table_names, $single_record);
                }
                return response()->json([
                    "status" => true,
                    "msg" => "All Record Found",
                    "data" => $table_names
                ]);
            }
        }
        if ($all_links) {
            foreach ($all_links as $link) {
                $link_array = explode('/', $link);
                $table_name = end($link_array);
                if ($table_name === $request->slug) {
                    $match = true;
                    $table_data = Http::get($link)->json();
                    if ($table_data) {
                        $fields = collect($table_data[$table_name][0])->keys()->toArray();
                        if (Schema::hasTable($table_name)) {
                            Schema::dropIfExists($table_name);
                        }
                        Schema::create($table_name, function (Blueprint $table) use ($fields) {
                            foreach ($fields as $value) {
                                $table->string($value)->nullable();
                            }
                        });
                        DB::statement("ALTER TABLE `$table_name` comment 'RTO master - " . now() . "'");
                        $rto_data_insert = collect($table_data[$table_name])->chunk(1000);
                        foreach ($rto_data_insert as $value) {
                            DB::table($table_name)->insert($value->toArray());
                        }
                    }
                    return response()->json([
                        "status" => true,
                        "msg" => "RTO synced successfully for $table_name",
                    ]);
                }
            }
        }
    }

    public function getVersionDetails($product_sub_type_id, $variant_id)
    {
        $product = [
            '1'  => 'motor',
            '2'  => 'bike',
            '6'  => 'pcv',
            '9'  => 'gcv',
            '13' => 'gcv',
            '14' => 'gcv',
            '15' => 'gcv',
            '16' => 'gcv',
        ];
        if (in_array($product_sub_type_id, array_keys($product))) {
            $env = config('app.env');
            $env_folder = ($env == 'local') ? 'uat' : 'production';

            $product = $product[$product_sub_type_id];
            $path = 'mmv_masters/' . $env_folder . '/';

            $file_name  = $path . $product . '_model_version.json';
            $variant_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($file_name), true);
            $variant_data = collect($variant_data)->where('model_id', $variant_id)->first();

            if (empty($variant_data)) {
                return [
                    'status' => false,
                    'data' => $variant_data
                ];
            } else {
                return [
                    'status' => true,
                    'data' => $variant_data
                ];
            }
            return  [
                'status' => false,
                'message' => 'Vehicle details not found.'
            ];
        }
    }
}
