<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\LogConfigurator;

class LogConfiguratorMasterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $log = LogConfigurator::whereNull('deleted_at')->get();
        return view('admin_lte.log_configurator_history.index', compact('log'));
    }

    public function create() {
        $tables = DB::connection()->getDoctrineSchemaManager()->listTableNames();
        return view('admin_lte.log_configurator_history.create', compact('tables'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'type_of_log' => 'required|string|max:150',
            'backup_bata_onward' => 'required',
            'log_rotation_frequency' => 'required',
            'log_to_retained' => 'required',
        ];
        if ($request->type_of_log == "Database") {
            $rules['database_table'] = 'required';
        } elseif ($request->communication_type == "File System") {
            $rules['location'] = 'required|string|max:200';
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        } else {
            $log_add = new LogConfigurator;
            $log_add->type_of_log = $request->type_of_log;
            $log_add->location_path = $request->location;
            $log_add->database_table = $request->database_table;
            $log_add->backup_onward = $request->backup_bata_onward;
            $log_add->log_rotation_frequency = $request->log_rotation_frequency;
            $log_add->log_to_retained = $request->log_to_retained;
            $result = $log_add->save();
            if($result) {
                return redirect()->route('admin.log_configurator.index')->with([
                    'status' => 'Log Configurator Added Successfully',
                    'class' => 'success'
                ]);
            } else {
                return back()->with([
                    'status' => 'Log Configurator Added Failed',
                    'class' => 'danger'
                ]);
            }
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    public function edit($id) {
        $log = LogConfigurator::where('log_configurator_id', $id)->first();
        return view('admin_lte.log_configurator_history.edit', compact('log'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $rules = [
            'type_of_log' => 'required|string|max:150',
            'backup_bata_onward' => 'required',
            'log_rotation_frequency' => 'required',
            'log_to_retained' => 'required',
        ];
        if ($request->type_of_log == "Database") {
            $rules['database_table'] = 'required';
        } elseif ($request->communication_type == "File System") {
            $rules['location'] = 'required|string|max:200';
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        } else {
            $log = LogConfigurator::where('log_configurator_id', $id)->update([
                'type_of_log' => $request->type_of_log,
                'location_path' => $request->location ?? null,
                'database_table' => $request->database_table ?? null,
                'backup_onward' => $request->backup_bata_onward,
                'log_rotation_frequency' => $request->log_rotation_frequency,
                'log_to_retained' => $request->log_to_retained
            ]);
            if($log) {
                return redirect()->route('admin.log_configurator.index')->with([
                    'status' => 'Log Configurator Updated Successfully',
                    'class' => 'success'
                ]);
            } else {
                return back()->with([
                    'status' => 'Log Configurator Updated Failed',
                    'class' => 'danger'
                ]);
            }
        }
        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $log = LogConfigurator::where('log_configurator_id', $id)->delete();
        if($log) {
            return redirect()->route('admin.log_configurator.index')->with([
                'status' => 'Log Configurator Deleted Successfully',
                'class' => 'success'
            ]);
        } else {
            return back()->with([
                'status' => 'Log Configurator Deleted Failed',
                'class' => 'danger'
            ]);
        }
    }
}
