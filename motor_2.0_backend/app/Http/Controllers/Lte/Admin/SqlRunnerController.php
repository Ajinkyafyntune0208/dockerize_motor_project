<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Exports\RunAndDownloadExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SqlRunnerController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->can('query_builder.list')) {
            return response('unauthorized action', 401);
        }

        if (!empty($request->sql_query)) {
            $data = [
                'sql_query' => $request->sql_query
            ];

            $records = $this->sanitizeRunSql($data);

            if (!$records['status']) {
                if ($records['is_validation_error']) {
                    return redirect()->route('admin.sql-runner')->withInput()->with([
                        'errors' => $records['message'],
                        'class' => 'warning',
                        'records' => []
                    ]);
                } elseif ($records['is_syntax_error']) {
                    return redirect()->route('admin.sql-runner')->withInput()->with([
                        'errors' => $records['message'],
                        'class' => 'danger',
                        'records' => []
                    ]);
                } else {
                    return redirect()->route('admin.sql-runner')->withInput()->with([
                        'errors' => 'Something went wrong While validating SQL',
                        'class' => 'warning',
                        'records' => []
                    ]);
                }
            }

            $headings = [];
            $records = $records['data'];
            if (count($records) > 1) {

                foreach ($records[0] as $key => $value) {
                    array_push($headings, $key);
                }
            } else {

                foreach ($records as $data) {
                    foreach ($data as $key => $value) {
                        array_push($headings, $key);
                    }
                }
            }
        }

        if ($request->has('errorbol') && $request->errorbol){
            return view('admin_lte.sql_runner.index');
        } elseif($request->download == 'rundownload' && !empty($request->sql_query)){
            return $this->runAndDownload($records,$headings);
        } elseif (isset($records) && isset($headings) ) {
            return view('admin_lte.sql_runner.index',compact('headings','records'));
        }

        return view('admin_lte.sql_runner.index');
    }

    private function sanitizeRunSql($data)
    {
        $valid = Validator::make($data, [
            'sql_query' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $disallowedKeywords = ['update ', 'truncate ', 'delete ', 'insert ', 'drop ', 'rename ', 'set ', 'replace ','altr '];
                    
                    // Check if the SQL query contains any disallowed keywords
                    foreach ($disallowedKeywords as $keyword) {
                        if (stripos(strtolower($value), $keyword) !== false) {
                            $fail("Using method: $keyword is Strictly prohibited");
                            // $fail("The :attribute contains a disallowed method: $keyword");
                        }
                    }
                    if (strpos($value, ';') !== false) {
                        $fail("Usage of semicolon (;) is Strictly prohibited");
                        // $fail("The :attribute contains a disallowed character: semicolon (;)");
                    }
                },
            ],
        ]);
        
        if ($valid->fails()) {

            $errors = $valid->errors()->get('sql_query')[0];

            return [
                'status'=> false,
                'message' => $errors,
                'is_validation_error' => true,
                'is_syntax_error' => false
            ];
        }
        if ($valid){

            try {

                $query = $data['sql_query'];
                // dd($query);
                $result = DB::select('Select '.DB::raw($query));

                return [
                    'status'=> true,
                    'message' => 'Query executed successfully.',
                    'data'=> $result
                ];

            } catch (\Exception $e) {
               
                return [
                    'status'=> false,
                    'message' => $e->getMessage(),
                    'is_validation_error' => false,
                    'is_syntax_error' => true
                ];
                // return ['syntax_error' => $e->getMessage()]; // 500 is the status code for Internal Server Error
            }
            
        }
    }

    public function runAndDownload($records,$headings)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new RunAndDownloadExport($records,$headings), 'Sqlruner '. now()->format('Y-m-d H-i-s') .'.xls');
    }
}
