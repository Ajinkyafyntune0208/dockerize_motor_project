<?php

namespace App\Http\Controllers\Lte\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PAinsuranceMaster;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Validator;

class pa_insurance_masters extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $pa_insurance_masters = PAinsuranceMaster::all();
        return view('admin_lte.pa_insurance_masters.index', compact('pa_insurance_masters'));
    }
    public static function getpainsurance()
    {
        $data = PAinsuranceMaster::all();
        return response()->json([
           'data' => $data,
           'status' => true
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'excelfile' => 'required|file|mimes:xlsx,xls|max:2048'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        try {
            if ($request->has('excelfile')) { //excelfile is the name of input type
                $file = $request->file('excelfile');
                $extension = $file->getClientOriginalExtension();
                if ($extension == 'xlsx' || $extension == 'xls') {
                    $path = $request->file('excelfile')->getRealPath();
                    $data = Excel::toArray([], $path, null, \Maatwebsite\Excel\Excel::XLSX)[0]; // it will return mulitple array each array with colume value
                    $insertdata = [];
                    foreach ($data as $key => $value) {
                        $temp['name'] = $value['1']; // 0 index is id but id will auto incrementt
                        $temp['value'] = $value['2']; // 1 is name 2 is value and 3 is partyid
                        $temp['partyid'] = $value['3'];
                        $insertdata[] = $temp;
                    }
                    unset($insertdata[0]); //    "partyid" => null    "value" => null  "name" => null
                    unset($insertdata[1]); //   "partyid" => "Partyid"   "value" => "Value" "name" => "Name"
                    PAinsuranceMaster::first() ? PAinsuranceMaster::truncate() : ''; //if exist it will become true and truncate the table  
                    PAinsuranceMaster::insert($insertdata);
                } else {
                    throw new \Exception('only xlsx or xls file allow');
                }
            }
        } catch (\Exception $e) {
            return redirect()->route('admin.pa-insurance-masters.index')->with([
                'status' => 'Something went wrong..!',
                'class' => 'danger',
            ]);
        }
        return redirect()->route('admin.pa-insurance-masters.index')->with([
            'status' => 'Data has been imported successfully..!',
            'class' => 'success',
        ]);
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
