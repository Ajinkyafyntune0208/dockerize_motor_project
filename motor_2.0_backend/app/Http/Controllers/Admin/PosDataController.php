<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\MasterCompany;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Validator;
use App\Models\Agents;

class PosDataController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('pos.list')) {
            return abort(403, 'Unauthorized action.');
        }
        $company = MasterCompany::whereIn('company_id', [40,28])->select('company_alias')->get();
        $table_data = [];
        $rules=[
            'table_name'=>'nullable'
        ];
        $validator = Validator::make(request()->all(), $rules);
        if($validator->fails()){
            return redirect()->back()->withErrors($validator);
        }
        if (request()->table_name != null ) {
            $table_name = (request()->table_name == "icici_lombard") ? request()->table_name.'_pos_mapping' : request()->table_name.'_pos_mappings';
            $table_data = DB::table($table_name)->get();
        }
        return view('pos_data.index', compact('table_data','company'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $table = request()->query('table');
        $pos = ($table == "icici_lombard") ? $table . '_pos_mapping' : $table . '_pos_mappings';
        $table_data = DB::table($pos)->where('ic_mapping_id', $id)->first();
        return view('pos_data.show', compact('table_data','table'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function agentList(Request $request)
    {
        $perPage = 50;  
        $lists = Agents::paginate($perPage);
        $list_counts = Agents::count();
        return view('pos_list.index', compact('lists','list_counts','perPage'));
    }
}
