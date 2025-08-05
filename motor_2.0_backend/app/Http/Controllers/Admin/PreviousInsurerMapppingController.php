<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PreviousInsurerExport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Imports\PreviousInsurerImport;
use App\Models\PreviousInsurerMapppingNew;
use Maatwebsite\Excel\Facades\Excel;
class PreviousInsurerMapppingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('previous_insurer_mappping.list')) {
            abort(403, 'Unauthorized action.');
        }
        $data=PreviousInsurerMapppingNew::all();
        return view('previous_insurer_mappping.index', compact('data'));
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
        $file = $request->file('previous_insurer_mapping');
        $extension = $file->getClientOriginalExtension();
        if ($extension == 'xlsx' || $extension == 'xls') {

            $insert_data = Excel::toArray(new PreviousInsurerImport, $file);
            $var = [];
            foreach ($insert_data as $key => $value) {
                if($key == 0){
                    continue;
                }
                $var[$key] = [
                    "previous_insurer" => $value[0],
                    "company_alias" => $value[1],
                    "oriental" => $value[2],
                    "acko" => $value[3],
                    "sbi" => $value[4],
                    "bajaj_allianz" => $value[5],
                    "bharti_axa" => $value[6],
                    "cholamandalam" => $value[7],
                    "dhfl" => $value[8],
                    "edelweiss" => $value[9],
                    "future_generali" => $value[10],
                    "godigit" => $value[11],
                    "hdfc_ergo" => $value[12],
                    "hdfc" => $value[13],
                    "hdfc_ergo_gic" => $value[14],
                    "icici_lombard" => $value[15],
                    "iffco_tokio" => $value[16],
                    "kotak" => $value[17],
                    "liberty_videocon" => $value[18],
                    "magma" => $value[19],
                    "national_insurance" => $value[20],
                    "raheja" => $value[21],
                    "reliance" => $value[22],
                    "royal_sundaram" => $value[23],
                    "shriram" => $value[24],
                    "tata_aig" => $value[25],
                    "united_india" => $value[26],
                    "universal_sompo" => $value[27],
                    "new_india" => $value[28],
                    "nic" => $value[29],
                ];
            }
            PreviousInsurerMapppingNew::truncate();
            $list =PreviousInsurerMapppingNew::insert($var);
            return redirect()->route('admin.previous-insurer-mapping.index')->with([
                'status' => 'Upload Success',
                'class' => 'success',
            ]);
            
        }else{
            return redirect()->route('admin.previous-insurer-mapping.index')->with([
                'status' => 'Choose excel file format Only!!!!',
                'class' => 'danger',
            ]);
        }
       
    }

    public function exportUsers(Request $request){
        if (PreviousInsurerMapppingNew::count() === 0) {
            return redirect()->route('admin.previous-insurer-mapping.index')->with([
                'status' => 'Empty data',
                'class' => 'info',

            ]);
        } else {
            return Excel::download(new PreviousInsurerExport, 'PreviousInsurerImport.xls');
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
