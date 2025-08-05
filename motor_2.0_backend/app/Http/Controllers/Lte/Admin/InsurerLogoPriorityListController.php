<?php

namespace App\Http\Controllers\Lte\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\MasterCompany;
use App\Models\InsurerLogoPriorityList;
use Illuminate\Support\Facades\DB;
use MongoDB\Operation\Update;

class InsurerLogoPriorityListController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $seller_type = ['B2B', 'B2C'];
        $company = MasterCompany::pluck('company_name', 'company_id');
        $data = InsurerLogoPriorityList::where('seller_type', 'B2B')->get() ?? '';
        $data1 = InsurerLogoPriorityList::where('seller_type', 'B2C')->get() ?? '';
        return view('admin_lte.insurer-priority-list.index', compact('company', 'seller_type', 'data', 'data1'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // InsurerLogoPriorityList::where('seller_type', 'B2B')->update(['priority' => 0]);
        // return redirect()->route('admin.insurer_logo_priority_list.index');
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
        $insert = [];
        $company_data = [];
        $updated_incoming_data = [];
        $is_b2b =  $request->all()['businessType'] == 'B2B' ? true : false;
        // $ins_logo_priority = new InsurerLogoPriorityList();
        $existing_data = InsurerLogoPriorityList::where('seller_type', $request->all()['businessType'])->pluck('company_id', 'priority')->toArray();
        $data_check = InsurerLogoPriorityList::where('seller_type', $request->all()['businessType'])->exists();
        foreach ($request->all()['insurers'] as $key => $value) {
            $company_data[$key + 1] = $value;
            $updated_incoming_data = $company_data;
            if ($value == "") {
                return [
                    'id' => "failureMessage",
                    'message' => "Please select all company for prority"
                ];
            }
        }
        $company_info = MasterCompany::whereIn('company_id', $company_data)->get()->toArray();
        if ($data_check) {
            for ($i = 1; $i <= count($existing_data); $i++) {
                if ($existing_data[$i] != $updated_incoming_data[$i]) {
                    self::update(new Request(['company_id' => $updated_incoming_data[$i], 'seller_type' => $request->all()['businessType']]), $i);
                    // return redirect()->route('admin.insurer_logo_priority_list.index');
                }
            }
            return redirect()->route('admin.insurer_logo_priority_list.index');
        }

        foreach ($request->all()['insurers'] as $key => $value) {
            $temp = [];
            foreach ($company_info as $k => $v) {
                if ($v['company_id'] == $value) {
                    $temp['company_id'] = $value;
                    $temp['company_name'] = $v['company_name'];
                    $temp['company_alias'] = $v['company_alias'];
                    $temp['priority'] = $key + 1;
                }
            }
            if ($value != "") {
                if ($is_b2b) {
                    $temp['seller_type'] = 'B2B';
                    $insert[] = $temp;
                } else if (!$is_b2b) {
                    $temp['seller_type'] = 'B2C';
                    $insert[] = $temp;
                }
            }
        }

        InsurerLogoPriorityList::insert($insert);

        return redirect()->route('admin.insurer_logo_priority_list.index');
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
        InsurerLogoPriorityList::where('company_id', $request->all()['company_id'])->where('seller_type', $request->all()['seller_type'])->update(['priority' => $id]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {}
    public function reset()
    {
        // InsurerLogoPriorityList::where('seller_type', 'B2C')->update(['priority' => 0]);
        // return redirect()->route('admin.insurer_logo_priority_list.index');
    }
}