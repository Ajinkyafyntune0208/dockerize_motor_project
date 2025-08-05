<?php

namespace App\Http\Controllers;

use App\Jobs\MasterDataManagementSync;
use App\Models\MdmSyncLogs;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use App\Models\MdmMaster;
use Illuminate\Support\Facades\Config;

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 1800);

class MasterDataManagementController extends Controller
{
    protected static $allowedTables;
    protected static $tempTablePrefix = '_mdm_temporary';
    protected $log_object;

    public function __construct()
    {
            $mdmMasterTables = MdmMaster::pluck('mdm_allowed_table')
            ->filter() 
            ->toArray();
        if(isset($mdmMasterTables) && !empty($mdmMasterTables))
        { 
            Config::set('mdm.allowedTables', $mdmMasterTables);
        }
        self::$allowedTables = config('mdm.allowedTables');
    }

    public function index()
    {
        return view('admin.mdm.index');
    }

    /**
     * An API call will be made to this function which will call MDM's API and return the response
     * @return json
     */
    public function getAllMasters()
    {
        try {
            if (!\App\Models\ThirdPartySetting::where('name', 'mdm-fetch-all-urls')->exists()) {
                return self::returnResponseAsJson(false, 'Master Data Management configuration is not done on this portal. Please check with the tech team.');
            }

            $fetchUrls = httpRequest('mdm-fetch-all-urls', [], [], [], [], false);
            if ($fetchUrls['status'] != 200) {
                return self::returnResponseAsJson(false, 'MDM Fetch API is not working. Got response status as ' . $fetchUrls['status']);
            }
            if (($fetchUrls['response']['status'] ?? false) == false) {
                $msg = isset($fetchUrls['response']['message']) ? ('MDM Fetch API failure message : ' . $fetchUrls['response']['message']) : 'MDM\'s Fetch API status is not true';
                return self::returnResponseAsJson(false, $msg);
            }
            $allMasters = $fetchUrls['response']['data'];
            if (!is_array($allMasters)) {
                return self::returnResponseAsJson(false, $fetchUrls['response']['message'] ?? 'MDM\'s fetch API status is not true');
            }
            $existingTablesWithComment = $this->getExistingTables();
            $existingTables = array_keys($existingTablesWithComment);
            foreach ($allMasters as $key => $value) {
                $allMasters[$key]['table_exists'] = in_array($value['master_name'], $existingTables);
                $allMasters[$key]['comment'] = $existingTablesWithComment[$value['master_name']] ?? null;
                if (!empty($allMasters[$key]['comment']) && stripos($allMasters[$key]['comment'], 'MDM master - ') !== false) {
                    $allMasters[$key]['last_updated_time'] = \Illuminate\Support\Carbon::parse(str_replace('MDM master - ', '', $allMasters[$key]['comment']))->diffForHumans();
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Data found',
                'data' => $allMasters,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong. MDM Fetch API is not working.',
                'errorMsg' => $e->getMessage(),
            ]);
        }
    }

    public function syncSingleMaster($master_id)
    {
        $this->log_object = MdmSyncLogs::create([
            'master_id' => $master_id,
            'status' => 'pending',
        ]);
        $syncStatus = $this->syncMaster($master_id);
        $original = $syncStatus->original;
        $is_status_false = isset($original['status']) && ($original['status'] === false);
        $is_status_true = isset($original['status']) && ($original['status'] === true);
        if ($is_status_true) {
            $status = 'success';
            $message = $original['message'];
            $response = self::returnResponseAsJson($original['status'], $original['message']);
        } else if ($is_status_false) {
            $status = 'failure';
            $message = $original['message'];
            $response = self::returnResponseAsJson($original['status'], $original['message']);
        } else {
            $status = 'failure';
            $message = 'Something went wrong while syncing single master : ' . $master_id;
            $response = self::returnResponseAsJson(false, $message);
        }
        $this->log_object->status = $status;
        $this->log_object->message = $message;
        $this->log_object->save();
        return $response;
    }
    /**
     *
     */
    public function syncMaster($master_id)
    {
        if (empty($master_id) || !is_numeric($master_id)) {
            return self::returnResponseAsJson(false, 'Master ID is mandatory.');
        }
        // Fetch the Master name from the ID specified
        try {
            $fetchMasterData = self::fetchMasterData($master_id);
            if ($fetchMasterData['status'] !== true) {
                $this->log_object->master_name = $fetchMasterData['table_name'] ?? null;
                return self::returnResponseAsJson(false, $fetchMasterData['message'] ?? 'Something went wrong while syncing the table. Please try again.');
            }
            $this->log_object->master_name = $fetchMasterData['table_name'];
            if (!$this->isTableAllowed($fetchMasterData['table_name'])) {
                return self::returnResponseAsJson(false, 'This table sync is restricted as it is not a master table.');
            }
            if (empty($total_rows = ($fetchMasterData['data']['master_details'][0]['rows_count'] ?? null))) {
                return self::returnResponseAsJson(false, 'This master has empty or zero records');
            }
            $this->log_object->total_rows = $total_rows;
            try {
                // MasterDataManagementSync::dispatch($master_id);
                $this->startTableSyncProcess(
                    $master_id,
                    $fetchMasterData['table_name'],
                    $fetchMasterData['data']['table_structure'],
                    $total_rows
                );
            } catch (\Exception $e) {
                Log::error($e, [
                    'MDM Sync Master ID' => $master_id,
                ]);
                return response()->json([
                    'status' => false,
                    'message' => $fetchMasterData['table_name'] . ' : Failed to sync the master table.',
                    'dev' => $e->getMessage(),
                ]);
            }

            return self::returnResponseAsJson(true, $fetchMasterData['table_name'] . ' : Master Sync success');
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while syncing the Master. Please try again.',
                'errorMsg' => $e->getMessage(),
            ]);
        }
    }

    public function syncAllMasters()
    {
        $allMasters = $this->getAllMasters();
        $d = $allMasters->original;
        $is_status_false = isset($d['status']) && ($d['status'] === false);
        $is_status_true = isset($d['status']) && ($d['status'] === true);
        if ($is_status_true) {
            collect($d['data'])->each(function ($master) {
                MasterDataManagementSync::dispatch($master['id'])->onQueue(config('mdm.queue.name'));
            });
            return self::returnResponseAsJson(true, 'All masters added in queue.');
        } else if ($is_status_false) {
            return self::returnResponseAsJson(false, $d['message']);
        }
        return self::returnResponseAsJson(false, 'Something went wrong while syncing all the masters. Please try again after sometime.');
    }

    public function startTableSyncProcess(int $master_id, String $table_name, array $table_structure, int $total_rows)
    {
        // createTemporaryTable
        $this->createTemporaryTable($table_name, $table_structure);

        // syncToTemporaryTable
        $this->syncToTemporaryTable($master_id, $total_rows);

        // switchToOriginalTable
        $this->switchToOriginalTable($table_name);
    }

    /**
     *
     */
    public function createTemporaryTable(String $table_name, array $table_structure)
    {
        // If table already exists no need to create temporary table.
        $temp_table_name = $table_name . self::$tempTablePrefix;
        if (Schema::hasTable($temp_table_name)) {
            Schema::dropIfExists($temp_table_name);
        }
        Schema::create($temp_table_name, function (Blueprint $table) use ($table_structure) {
            foreach ($table_structure as $key => $value) {
                if ($value['datatype'] == 'integer') {
                    $table->{strtolower($value['datatype'])}($value['column_name'], (int) $value['length'], false)->nullable()->autoIncrement(false);
                } else {
                    $table->{strtolower($value['datatype'])}($value['column_name'], (int) $value['length'])->nullable();
                }
                if (($value['is_indexed'] ?? 0) === 1) {
                    $table->index($value['column_name']);
                }
            }
        });
        DB::statement("ALTER TABLE `$temp_table_name` comment 'MDM master - " . now() . "'");
    }

    public function syncToTemporaryTable($master_id, $totalRows)
    {
        $perPage = 10000;
        $currentPage = 1;
        $continue = true;
        $totalPages = ceil($totalRows / $perPage);
        $rows_inserted = 0;
        while ($continue) {

            if ($currentPage == $totalPages) {
                $continue = false;
            }
            $data = self::fetchMasterData($master_id, $perPage, $currentPage);

            if ($data['status'] !== true) {
                throw new \Exception('Error occured while fetching the master Data. Syncing stopped. ' . $data['message'] ?? '');
            }

            if (!isset($temp_table_name)) {
                $temp_table_name = $data['table_name'] . self::$tempTablePrefix;
            }

            $data_insert = collect($data['data']['data'])->chunk(500);
            $rows_inserted += count($data['data']['data']);
            foreach ($data_insert as $key => $value) {
                DB::table($temp_table_name)->insert($value->toArray());
            }
            $this->log_object->rows_inserted = $rows_inserted;
            $this->log_object->save();

            $currentPage++;
        }
        return true;
    }

    /**
     *
     */
    public function switchToOriginalTable(String $table_name)
    {
        $temp_table_name = $table_name . self::$tempTablePrefix;
        if (!Schema::hasTable($temp_table_name)) {
            throw new \Exception('Table does not exist.');
        }
        // Drop old master table
        if (!$this->isTableAllowed($table_name)) {
            throw new \Exception('This table sync is restricted as it is not a master table.');
        }
        Schema::dropIfExists($table_name);

        //Rename temporary table to original master table's name
        DB::statement("RENAME TABLE `$temp_table_name` TO `$table_name`;");
    }

    public function dropInsertTable($table_name, $data, $table_structure)
    {
        try {
            if (!in_array($table_name, $this->allowedTables)) {
                return false;
            }
            if (Schema::hasTable($table_name)) {
                Schema::dropIfExists($table_name);
            }
            Schema::create($table_name, function (Blueprint $table) use ($table_structure) {
                foreach ($table_structure as $key => $value) {
                    if ($value['datatype'] == 'integer') {
                        $table->{strtolower($value['datatype'])}($value['column_name'], (int) $value['length'], false)->nullable()->autoIncrement(false);
                    } else {
                        $table->{strtolower($value['datatype'])}($value['column_name'], (int) $value['length'])->nullable();
                    }
                }
            });
            DB::statement("ALTER TABLE `$table_name` comment 'MDM master - " . now() . "'");
            $data_insert = collect($data)->chunk(1000);
            foreach ($data_insert as $key => $value) {
                DB::table($table_name)->insert($value->toArray());
            }
            return true;
        } catch (\Exception $e) {
            Log::error($e);
            return false;
        }
        return false;
    }

    public function getExistingTables(): array
    {
        $defaultConnection = config('database.default');
        $dbName = config('database.connections.' . $defaultConnection)['database'] ?? '';
        $allTables = DB::select("SELECT TABLE_NAME, TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE table_schema = '" . $dbName . "'");
        $allTables = json_decode(json_encode($allTables), true);
        $tempTable = [];
        foreach ($allTables as $tk => $tv) {
            $tempTable[$tv['TABLE_NAME']] = $tv['TABLE_COMMENT'];
        }
        $allTables = $tempTable;
        unset($tempTable);
        return $allTables;
    }

    public static function fetchMasterData($master_id, $per_page = 1, $page_no = 1)
    {
        $setting = \App\Models\ThirdPartySetting::where('name', 'mdm-fetch-single-master')->first();
        if (empty($setting)) {
            return [
                'status' => false,
                'message' => 'MDM : Fetching single master data is not configured. Please check with the tech team.',
            ];
        }

        $fetchMaster = httpRequestNormal($setting->url . $master_id, $setting->method, [
            "perpage" => (int) $per_page,
            "page" => (int) $page_no,
        ], [], $setting->headers, [], false);

        if ($fetchMaster['status'] != 200) {
            return [
                'status' => false,
                'message' => 'MDM Fetch API is not working. Got response status as ' . $fetchMaster['status'],
            ];
        }
        $fetchMasterResponse = $fetchMaster['response'];
        if ($fetchMasterResponse['status'] !== true) {
            return [
                'status' => false,
                'message' => 'MDM API failure : ' . ($fetchMasterResponse['message'] ?? ''),
            ];
        }
        $masterDetails = $fetchMasterResponse['master_details'][0];
        $tableName = $masterDetails['master_name'];
        $rowsCount = $masterDetails['rows_count'];
        $data = $fetchMasterResponse['data'];
        if ((is_array($data) && count($data) == 0) || !is_array($data)) {
            return [
                'status' => false,
                'message' => $tableName . ' : Sync stopped as the master has empty records.',
                'table_name' => $tableName,
            ];
        }
        return [
            'status' => true,
            'table_name' => $tableName,
            'total_rows' => $rowsCount,
            'data' => $fetchMasterResponse,
        ];
    }

    public static function returnResponseAsJson(bool $status = false, String $message = '')
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
        ]);
    }
    public static function getMdmLogs(Request $request)
    {
        if (!auth()->user()->can('master.sync.logs')) {
            return response('unauthorized action', 401);
        }
        if($request->form_submit){
            $validator =  Validator::make($request->all(),[
                'from_date' =>'nullable|date',
                'to_date' =>'nullable|date',
                'master_name' =>'nullable|string',
            ]);
            if($validator->fails()){
                return redirect()->back()->withInput()->withErrors($validator->errors());
            }
        }
        $mdmlogs = MdmSyncLogs::
            when(!empty($request->master_name), function ($query) use ($request) {
            return $query->where('master_name', [$request->master_name]);
        })
            ->when(!empty($request->status), function ($query) use ($request) {
                return $query->where('status', [$request->status]);
            })
            ->when(!empty($request->from_date || $request->to_date), function ($query) {
                $query->whereBetween('updated_at', [
                    date('Y-m-d H:i:s', strtotime(request()->from_date)),
                    date('Y-m-d 23:59:59', strtotime(request()->to_date ?? request()->from_date)),
                ]);
            })
            ->orderBy('updated_at', 'desc')
            ->paginate($request->per_page ?? 25);

        return view('admin.mdm.view', compact('mdmlogs'));
    }

    public static function isTableAllowed($tableName)
    {
        return (in_array($tableName, self::$allowedTables)
            || (\Illuminate\Support\Str::contains($tableName, '_cashless_garage')));
    }
    
}
