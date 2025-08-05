<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\RtoImport;
use App\Models\MasterRto;
use App\Models\MasterState;
use App\Models\MasterZone;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MasterRtoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //

        if (!auth()->user()->can('rto_master.list')) {
            abort(403, 'Unauthorized action.');
        }

        $rto_master = MasterRto::join('master_state as ms', 'ms.state_id', 'master_rto.state_id')
                        ->join('master_zone as mz', 'mz.zone_id', 'master_rto.zone_id')->get();

        // dd($rto_master);

        return view('developer_tool.rto_master.index', compact('rto_master'));

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

        $insert_data = Excel::toArray(new RtoImport, $request->file('rto_master_file'));

        $var = [];

        // dd($insert_data);

        foreach ($insert_data[0] as $key => $value) {
            $var[$key] = [
                "rto_group_id" => $value['rto_group_id'],
                "state_id" => $value['state_id'],
                "zone_id" => $value['zone_id'],
                "rto_code" => $value['rto_code'],
                "rto_number" => $value['rto_number'],
                "rto_name" => $value['rto_name'],
                "status" => $value['status'],
            ];
        }

        MasterRto::insert($var);

        return redirect()->back();


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

        if (!auth()->user()->can('user.list')) {
            abort(403, 'Unauthorized action.');
        }

        try {

            $rto = MasterRto::find($request->id);

            $rto->rto_code = $request->rto_code;
            $rto->rto_number = $request->rto_code;
            $rto->rto_name = $request->rto_name;
            $rto->state_id = $request->rto_state;
            $rto->zone_id = $request->rto_zone;
            $rto->status = $request->rto_status;

            $rto->save();

            return redirect()->route('admin.rto-master.index')->with([
                'status' => 'RTO Updated Successfully..!',
                'class' => 'success',
            ]);

        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
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
        //
    }


    public function get_state()
    {
        $state = MasterState::get();
        return $state;
    }

    public function get_zone()
    {
        $zone = MasterZone::get();
        return $zone;
    }
}
