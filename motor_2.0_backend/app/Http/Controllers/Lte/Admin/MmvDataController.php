<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrokerDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\BikeModel;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MmvDataExport;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class MmvDataController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        if (!auth()->user()->can('mmv_data.list')) {
            return abort(403, 'Unauthorized action.');
        }
        if (config('app.env') == 'local') {
            $env_folder = 'uat';
        } else if (config('app.env') == 'test') {
            $env_folder = 'production';
        } else if (config('app.env') == 'live') {
            $env_folder = 'production';
        }
        $data = [];
        $files = Storage::files('mmv_masters/' . $env_folder);
        $file_names = [
            'abibl_city_master',
            'abibl_insurer_master',
            'abibl_mg_mapping',
            'abibl_state_master',
            'archive_model_version',
            'bike_manufacturer',
            'bike_model',
            'bike_model_version',
            'coco_model_master',
            'fyntune_city_master',
            'fyntune_fastlane_bike_relation',
            'fyntune_fastlane_car_relation',
            'fyntune_financier_master',
            'fyntune_pincode_city_master',
            'fyntune_rb_bike_relation',
            'fyntune_rb_car_relation',
            'fyntune_rto_master',
            'fyntune_state_master',
            'gcv_model',
            'gcv_manufacturer',
            'gcv_model_version',
            'master_city',
            'master_rto',
            'master_state',
            'misc_manufacturer',
            'misc_model',
            'misc_model_version',
            'motor_model',
            'motor_manufacturer',
            'motor_model_version',
            'nic_model_master',
            'pcv_manufacturer',
            'pcv_model',
            'pcv_model_version',
            'tml_fyntune_model_master'
        ];
        foreach ($files as $key => $file) {
            $file_parts = explode('/', $file);
            $path_file = end($file_parts);
            $path_file = str_replace('.json', '', $path_file);
            if (auth()->user()->roles[0]->name !== "Admin" && in_array($path_file, $file_names)) {
                unset($files[$key]);
            }
        }
        if (request()->form_submit) {
            $validator = Validator::make(request()->all(),[
                'file_name'=>'required',
            ]);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator->errors());
            }
            if (request()->file_download != null) {
                return $this->downloadExcel($request);
            } else {
                if (request()->file_name != null) {

                    $data = collect(json_decode(Storage::get(request()->file_name), true));
                }
                $borker_details = BrokerDetail::all();
                return view('admin_lte.mmv_data.index', compact('borker_details', 'files', 'data'));
            }
        }
        $borker_details = BrokerDetail::all();
        return view('admin_lte.mmv_data.index', compact('borker_details', 'files', 'data'));
    }
    public function downloadExcel(Request $request)
    {
        $file_name = $request->file_name;
        $parts = explode('/', $file_name);
        $last_part = end($parts);
        $file_name = pathinfo($last_part, PATHINFO_FILENAME);

        // dd($file_name_with_extension);
        return Excel::download(new MmvDataExport, $file_name . '.xls');
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

        if (!auth()->user()->can('mmv_data.edit')) {
            return abort(403, 'Unauthorized action.');
        }
        $rules=[
            'url' => 'required'
        ];
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }
        try {
            \Illuminate\Support\Facades\Http::withoutVerifying()->get($request->url . '/api/car/getdata');
            return redirect()->route('admin.mmv-data.index')->with([
                'status' => 'Mmv Updated Successfully',
                'class' => 'success',
            ]);
        } catch (\Exception $e) {
            return redirect()->route('admin.mmv-data.index')->with([
                'status' => 'Mmv Updated Failed',
                'class' => 'danger',
            ]);
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
}
