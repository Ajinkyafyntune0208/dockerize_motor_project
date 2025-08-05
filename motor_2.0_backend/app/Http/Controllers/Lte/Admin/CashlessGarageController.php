<?php

namespace App\Http\Controllers\Lte\Admin;

use Illuminate\Http\Request;
use App\Models\MasterCompany;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Validator;

class CashlessGarageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin_lte.cashless_garage.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
            $company_alias = MasterCompany::pluck('company_alias')->filter();
            if (isset($request->file) && $request->file == 'csv_file') {
                
                return view('admin_lte.cashless_garage.upload', compact('company_alias'));
            }
       
       
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
            'company_alias' => 'required',
            'product' => 'required',
            'cashless_garage_file'=>'required'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }
        $file = $request->file('cashless_garage_file')->getClientOriginalName();

        $fileName = pathinfo($file,PATHINFO_FILENAME);
        $table_name = $request->company_alias.'_'.$request->product.'_cashless_garage';

        if (!Schema::hasTable("$table_name"))  {
            Schema::create("$table_name", function (Blueprint $table) {
                $table->string("garage_name")->nullable();
                $table->string("pincode")->index()->nullable();
                $table->string("mobile")->nullable();
                $table->string("vehicle_type")->nullable();
                $table->string("city_name")->index()->nullable();
                $table->string("address")->nullable();  
            });            
        } else {
            DB::table("$table_name")->truncate();
        }

        $is_excel = false;
        if ($request->hasFile('cashless_garage_file')) {
            $d = $request->file('cashless_garage_file');
            if (!in_array($d->getClientOriginalExtension(), ['xlsx'])) {
                return redirect(url()->previous())->with([
                    'status' => 'The file format should be Excel xlsx',
                    'class' => 'danger',
                ]);
            }
            $is_excel = true;
            $insert_data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\CashlessGarageImport, $request->file('cashless_garage_file'))[0];
            
            $validator = Validator::make($insert_data, [
                '*.garage_name' => ['required'],
                '*.pincode' => ['required'],
                '*.mobile' => ['required'],
                '*.vehicle_type' => ['required'],
                '*.address' => ['required'],
            ]);
            
            if ($validator->fails()) {
                return redirect()->route('admin.cashless_garage.index')->with([
                    "status" => $validator->errors(),
                    'class' => 'danger',
                ]);
            }

            DB::table("$table_name")->insert($insert_data);
        }
      
        return redirect()->route('admin.cashless_garage.index')->with([
            'status' => "Record Created Successfull in $table_name",
            'class' => 'success',
        ]);
    }
}
