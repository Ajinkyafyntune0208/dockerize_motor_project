<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MasterOccupation;
use App\Models\MasterOccupationName;
use App\Models\MasterCompany;
use Illuminate\Support\Facades\Validator;

class MasterOccupationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('master_occupation.list')) {
            abort(403, 'Unauthorized action.');
        }

        $occuption = MasterOccupation::all();

        $Destinctoccuption = MasterOccupationName::orderBy('occupation_name')->get();
        $company = MasterCompany::select('company_alias')->orderBy('company_alias')->get();

        return view('Occuption.index', compact('occuption','Destinctoccuption', 'company'));
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
        if (!auth()->user()->can('user.list')) {
            abort(403, 'Unauthorized action.');
        }

        $rules=[
            'occupation_code'=>'required',
            'occupation_name'=>'required',
            'company_alias'=>'required'
        ];
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            return redirect()->back()->withErrors($validator)->withInput();
        }else{
            $occuption = MasterOccupation::where([['company_alias',$request->company_alias],['occupation_name', $request->occupation_name]])->first();
            if ($occuption != null) {
                return redirect()->route('admin.master-occuption.index')->with([
                    'status' => 'Already present please update it....! ',
                    'class' => 'danger',
                    ]);
            }
            try{
                $insert_data=[
                    'occupation_code'=>$request->occupation_code,
                    'occupation_name'=>$request->occupation_name,
                    'company_alias'=>$request->company_alias
                ];
                $count=\Illuminate\Support\Facades\DB::table('master_occupation')->count();
                $insert_data['occupation_id']=$count+1;
                \Illuminate\Support\Facades\DB::table('master_occupation')->insert($insert_data);
                //MasterOccupation::create($request->all());

                return redirect()->route('admin.master-occuption.index')->with([
                    'status' => 'Occupation Added Successfully..!',
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

        if (!auth()->user()->can('user.list')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $occuption = MasterOccupation::find($request->id);
            $occuption -> occupation_code = $request -> occupation_code;
        
            $occuption -> save();

            
            return redirect()->route('admin.master-occuption.index')->with([
                'status' => 'Occuption Updated Successfully..!',
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
        if (!auth()->user()->can('user.list')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            MasterOccupation::destroy($id);

            return redirect()->route('admin.master-occuption.index')->with([
                'status' => 'Occuption Deleted..!',
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
