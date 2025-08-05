<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UspController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('usp.list')) {
            return abort(403, 'Unauthorized action.');
        }
        $bike_usps = \Illuminate\Support\Facades\DB::table('bike_usp')->orderBy('bike_usp_id', 'desc')->get();
        $car_usps = \Illuminate\Support\Facades\DB::table('car_usp')->orderBy('car_usp_id', 'desc')->get();
        $pcv_usps = \Illuminate\Support\Facades\DB::table('pcv_usp')->orderBy('pcv_usp_id', 'desc')->get();
        $gcv_usps = \Illuminate\Support\Facades\DB::table('gcv_usp')->orderBy('gcv_usp_id', 'desc')->get();
        $misc_usps = \Illuminate\Support\Facades\DB::table('misc_usp')->orderBy('misc_usp_id', 'desc')->get();
        //dd($misc_usps);
        // return compact('bike_usps', 'car_usps', 'pcv_usps', 'gcv_usps');
        return view('admin_lte.usp.index', compact('bike_usps', 'car_usps', 'pcv_usps', 'gcv_usps','misc_usps'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!auth()->user()->can('usp.create')) {
            return abort(403, 'Unauthorized action.');
        }
        $master_companies = \Illuminate\Support\Facades\DB::table('master_company')->whereNotNull('company_alias')->get();
        return view('admin_lte.usp.create', compact('master_companies'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('usp.create')) {
            return abort(403, 'Unauthorized action.');
        }
        
        $rules =[
            'usp_desc' => ['required','string','max:255'],
            'ic_alias' => ['required','string'],
            'usp_type' => ['required','string'],
        ];
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            return redirect()->back()->withErrors($validator)->withInput();
        }else{
             // if ($request->hasFile('usp_file')) {
            //$insert_data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\UspImport, $request->file('usp_file'))[0];
            // } else {
           
                $validateData= $request->query();
                $insert_data = [
                    'usp_desc' => $request->usp_desc,
                    'ic_alias' => $request->ic_alias,
                ];
            // }
                try {
                    if ($validateData['usp_type'] == "car") {
                        $count=\Illuminate\Support\Facades\DB::table('car_usp')->max('car_usp_id');
                        $insert_data['car_usp_id']=$count+1;
                        \Illuminate\Support\Facades\DB::table('car_usp')->insert($insert_data);
                    } elseif ($validateData['usp_type'] == "bike") {
                        $count=\Illuminate\Support\Facades\DB::table('bike_usp')->max('bike_usp_id');
                        $insert_data['bike_usp_id']=$count+1;
                        \Illuminate\Support\Facades\DB::table('bike_usp')->insert($insert_data);
                    } elseif ($validateData['usp_type'] == "pcv") {
                        $count=\Illuminate\Support\Facades\DB::table('pcv_usp')->max('pcv_usp_id');
                        $insert_data['pcv_usp_id']=$count+1;
                        \Illuminate\Support\Facades\DB::table('pcv_usp')->insert($insert_data);
                    } elseif ($validateData['usp_type'] == "gcv") {
                        $count=\Illuminate\Support\Facades\DB::table('gcv_usp')->max('gcv_usp_id');
                        $insert_data['gcv_usp_id']=$count+1;
                        \Illuminate\Support\Facades\DB::table('gcv_usp')->insert($insert_data);
                    }elseif ($validateData['usp_type'] == "misc") {
                        $count=\Illuminate\Support\Facades\DB::table('misc_usp')->max('misc_usp_id');
                        $insert_data['misc_usp_id']=$count+1;
                        \Illuminate\Support\Facades\DB::table('misc_usp')->insert($insert_data);
                    }
                    return redirect()->route('admin.usp.index')->with([
                        'status' => 'USP Created Successfully..!',
                        'class' => 'success',
                    ]);
                } catch (\Exception $e) {
                    return redirect()->back()->with([
                        'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                        'class' => 'danger',
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

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!auth()->user()->can('usp.edit')) {
            return abort(403, 'Unauthorized action.');
        }
        if (request()->usp_type == 'car') {
            $usp = \Illuminate\Support\Facades\DB::table('car_usp')->where([
                'car_usp_id' => $id,
            ])->first();
        } elseif (request()->usp_type == 'bike') {
            $usp = \Illuminate\Support\Facades\DB::table('bike_usp')->where([
                'bike_usp_id' => $id,
            ])->first();
        } elseif (request()->usp_type == 'pcv') {
            $usp = \Illuminate\Support\Facades\DB::table('pcv_usp')->where([
                'pcv_usp_id' => $id,
            ])->first();
        } elseif (request()->usp_type == 'gcv') {
            $usp = \Illuminate\Support\Facades\DB::table('gcv_usp')->where([
                'gcv_usp_id' => $id,
            ])->first();
        }elseif (request()->usp_type == 'misc') {
            $usp = \Illuminate\Support\Facades\DB::table('misc_usp')->where([
                'misc_usp_id' => $id,
            ])->first();
        }
        // return $usp;
        $master_companies = \Illuminate\Support\Facades\DB::table('master_company')->whereNotNull('company_alias')->get();
        return view('admin_lte.usp.edit', compact('usp', 'master_companies', 'id'));
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
        if (!auth()->user()->can('usp.edit')) {
            return abort(403, 'Unauthorized action.');
        }
        $rules =[
            'usp_desc' => ['required','string','max:255'],
            'ic_alias' => ['required','string'],
            'usp_type' => ['required','string'],
        ];
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            return redirect()->back()->withErrors($validator)->withInput();
        }
        $validateData = $request->only('usp_desc','ic_alias','usp_type');

        try {

            if ($validateData['usp_type'] == 'car') {
                \Illuminate\Support\Facades\DB::table('car_usp')
                    ->where('car_usp_id', $id)
                    ->update([
                        'usp_desc' => $validateData['usp_desc'],
                        'ic_alias' => $validateData['ic_alias'],
                    ]);
            } elseif ($validateData['usp_type'] == 'bike') {
                \Illuminate\Support\Facades\DB::table('bike_usp')
                    ->where('bike_usp_id', $id)
                    ->update([
                        'usp_desc' => $validateData['usp_desc'],
                        'ic_alias' => $validateData['ic_alias'],
                    ]);
            } elseif ($validateData['usp_type'] == 'pcv') {
                \Illuminate\Support\Facades\DB::table('pcv_usp')
                    ->where('pcv_usp_id', $id)
                    ->update([
                        'usp_desc' => $validateData['usp_desc'],
                        'ic_alias' => $validateData['ic_alias'],
                    ]);
            } elseif ($validateData['usp_type'] == 'gcv') {
                \Illuminate\Support\Facades\DB::table('gcv_usp')
                    ->where('gcv_usp_id', $id)
                    ->update([
                        'usp_desc' => $validateData['usp_desc'],
                        'ic_alias' => $validateData['ic_alias'],
                    ]);
            }elseif ($validateData['usp_type'] == 'misc') {
                \Illuminate\Support\Facades\DB::table('misc_usp')
                    ->where('misc_usp_id', $id)
                    ->update([
                        'usp_desc' => $validateData['usp_desc'],
                        'ic_alias' => $validateData['ic_alias'],
                    ]);
            }
            return redirect()->route('admin.usp.index')->with([
                'status' => 'USP Updated Successfully..!',
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
        if (!auth()->user()->can('usp.delete')) {
            return abort(403, 'Unauthorized action.');
        }
        try {

            if (request()->usp_type == 'car') {
                \Illuminate\Support\Facades\DB::table('car_usp')
                    ->where('car_usp_id', $id)
                    ->delete();
            } elseif (request()->usp_type == 'bike') {
                \Illuminate\Support\Facades\DB::table('bike_usp')
                    ->where('bike_usp_id', $id)
                    ->delete();
            } elseif (request()->usp_type == 'pcv') {
                \Illuminate\Support\Facades\DB::table('pcv_usp')
                    ->where('pcv_usp_id', $id)
                    ->delete();
            } elseif (request()->usp_type == 'gcv') {
                \Illuminate\Support\Facades\DB::table('gcv_usp')
                    ->where('gcv_usp_id', $id)
                    ->delete();
            }elseif (request()->usp_type == 'misc') {
                \Illuminate\Support\Facades\DB::table('misc_usp')
                    ->where('misc_usp_id', $id)
                    ->delete();
            }
            return redirect()->route('admin.usp.index')->with([
                'status' => 'USP Deleted Successfully..!',
                'class' => 'success',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }

    public function uspSample()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\UspSampleExport, 'USP Sample.xls');
    }
}
