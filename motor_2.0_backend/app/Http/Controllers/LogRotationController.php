<?php

namespace App\Http\Controllers;

use App\Models\LogRotationModel;
use App\Models\S3LogTablesModel;
use App\Models\ThirdPartyApiRequestResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use ZipArchive;

class LogRotationController extends Controller
{
    public function index(Request $request)
    {
        $list = LogRotationModel::select('log_rotation_id', 'type_of_log', 'location', 'db_table', 'backup_data_onwards', 'log_rotation_frequency', 'log_to_be_retained')->get();
        return view('log_rotation.index', compact('list'));
    }
    public function create(Request $request)
    {
        $logTables = S3LogTablesModel::select('table_name')->get();
        return view('log_rotation.create', compact('logTables'));
    }
    public function edit($id)
    {
        $logTables = S3LogTablesModel::select('table_name')->get();
        $data = LogRotationModel::find($id);
        if ($data) {
            return view('log_rotation.edit', compact('logTables', 'data'));
        } else {
            return redirect()->back()->with([
                'class' => 'danger',
                'message' => 'Record Not Found.'
            ]);
        }
    }
    public function destroy($id)
    {
        $record = LogRotationModel::find($id);
        if ($record) {
            $record->delete();
            return redirect()->back()->with([
                'class' => 'success',
                'message' => 'Record Deleted Successfully.'
            ]);
        } else {
            return redirect()->back()->with([
                'class' => 'danger',
                'message' => 'Record Not Found.'
            ]);
        }
    }
    public function store(Request $request)
    {
        $rules = [
            'type_of_log' => 'required|in:file,database',
            'location' => [
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->input('type_of_log') === 'file') {
                        if (empty($value)) {
                            $fail('The location field is required when type_of_log is file.');
                        } elseif (!is_string($value)) {
                            $fail('The location must be a string.');
                        } elseif (strlen($value) > 255) {
                            $fail('The location may not be greater than 255 characters.');
                        }
                    }
                },
            ],
            'db_table' => [
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->input('type_of_log') === 'database') {
                        if (empty($value)) {
                            $fail('The db_table field is required when type_of_log is database.');
                        } elseif (!is_string($value)) {
                            $fail('The db_table must be a string.');
                        } elseif (!\Illuminate\Support\Facades\DB::table('s3_log_tables')->where('table_name', $value)->exists()) {
                            $fail('The selected db_table is invalid.');
                        }
                    }
                },
            ],
            'backup_data_onwards' => 'required|integer|min:1|max:365',
            'log_rotation_frequency' => 'required|in:daily,weekly,monthly,quarterly,yearly',
            'log_to_be_retained' => 'required|integer|min:1|max:365',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }
        try {

            $logRotation = new LogRotationModel();
            $logRotation->type_of_log = $request->type_of_log;
            $logRotation->location = $request->type_of_log === 'file' ? $request->location : null;
            $logRotation->db_table = $request->type_of_log === 'database' ? $request->db_table : null;
            $logRotation->backup_data_onwards = $request->backup_data_onwards;
            $logRotation->log_rotation_frequency = $request->log_rotation_frequency;
            $logRotation->log_to_be_retained = $request->log_to_be_retained;
            $logRotation->save();
            return redirect()->route('admin.log_rotation.index')->with([
                'class' => 'success',
                'message' => 'Record Added Successfully.'
            ]);
        } catch (Exception $e) {
            return redirect()->back()->with([
                'class' => 'danger',
                'message' => 'Error While Adding Record.'
            ]);
        }
    }
    public function update(Request $request, $id)
    {
        $rules = [
            'type_of_log' => 'required|in:file,database',
            'location' => [
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->input('type_of_log') === 'file') {
                        if (empty($value)) {
                            $fail('The location field is required when type_of_log is file.');
                        } elseif (!is_string($value)) {
                            $fail('The location must be a string.');
                        } elseif (strlen($value) > 255) {
                            $fail('The location may not be greater than 255 characters.');
                        }
                    }
                },
            ],
            'db_table' => [
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->input('type_of_log') === 'database') {
                        if (empty($value)) {
                            $fail('The db_table field is required when type_of_log is database.');
                        } elseif (!is_string($value)) {
                            $fail('The db_table must be a string.');
                        } elseif (!\Illuminate\Support\Facades\DB::table('s3_log_tables')->where('table_name', $value)->exists()) {
                            $fail('The selected db_table is invalid.');
                        }
                    }
                },
            ],
            'backup_data_onwards' => 'required|integer|min:1|max:365',
            'log_rotation_frequency' => 'required|in:daily,weekly,monthly,quarterly,yearly',
            'log_to_be_retained' => 'required|integer|min:1|max:365',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }
        try {
            $logRotation = LogRotationModel::find($id);
            if (!$logRotation) {
                return redirect()->back()->with([
                    'class' => 'danger',
                    'message' => 'Record Not Found.'
                ]);
            }
            $logRotation->type_of_log = $request->type_of_log;
            $logRotation->location = $request->type_of_log === 'file' ? $request->location : null;
            $logRotation->db_table = $request->type_of_log === 'database' ? $request->db_table : null;
            $logRotation->backup_data_onwards = $request->backup_data_onwards;
            $logRotation->log_rotation_frequency = $request->log_rotation_frequency;
            $logRotation->log_to_be_retained = $request->log_to_be_retained;
            $logRotation->save();
            return redirect()->route('admin.log_rotation.index')->with([
                'class' => 'success',
                'message' => 'Record Updated Successfully.'
            ]);
        } catch (Exception $e) {
            return redirect()->back()->with([
                'class' => 'danger',
                'message' => 'Error While Updating Record.'
            ]);
        }
    }
    public function isS3Configured()
    {
        try {
            $files = Storage::disk('s3')->allFiles('/');
            if (empty($files)) {
                return response()->json([
                    'status' => false,
                    'errorSpecific' => 'The bucket is empty.',
                ]);
            } else {
                return response()->json([
                    'status' => true,
                    'files' => $files,
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'errorSpecific' => $e->getMessage(),
            ]);
        }
    }
    public function pushLogToS3()
    {
        $files = [];
        for ($i = 0; $i < 2; $i++) {

            $logs = ThirdPartyApiRequestResponses::limit(100)->get();

            $headers = array_keys($logs->first()->toArray());
            $name = generateRandomString(4);
            $csvFileName = "qqtp_req_res" . $name . ".csv";
            $handle = fopen($csvFileName, 'w');

            fputcsv($handle, $headers);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->name,
                    $log->url,
                    $log->request,
                    str_replace(',', "[comma]", $log->response),
                    $log->headers,
                    $log->response_headers,
                    $log->options,
                    $log->response_time,
                    $log->http_status,
                    $log->created_at->toDateString(),
                    $log->updated_at->toDateString(),
                ]);
            }
            fclose($handle);

            $csvContents = file_get_contents($csvFileName);

            $gzCompressedData = gzencode($csvContents);

            $gzFileName = $csvFileName . '.gz';

            file_put_contents($gzFileName, $gzCompressedData);

            $filePath = 'motor_db_logs/' . $gzFileName;
            $files = [...$files, public_path($gzFileName)];

            Storage::disk('public')->put($filePath, $gzCompressedData);

            unlink($csvFileName);
        }

        $zip = new ZipArchive;
        $zipFileName = generateRandomString(5) . '.zip';
        $zipFilePath = public_path($zipFileName);

        if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
            $filesToZip = $files;

            foreach ($filesToZip as $file) {
                $zip->addFile($file, basename($file));
            }

            $zip->close();

            Storage::disk('s3')->put('motor_db_logs/' . $zipFileName, file_get_contents($zipFilePath));
            foreach ($filesToZip as $file) {
                unlink($file);
            }
            unlink($zipFilePath);

            return response()->json(['message' => 'Zip file has been saved to public storage'], 200);
        } else {
            return response()->json(['error' => 'Failed to create the zip file'], 500);
        }
    }
}
