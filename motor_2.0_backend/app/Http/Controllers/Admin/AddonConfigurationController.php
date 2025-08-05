<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AddonConfigurationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('addon_configuration.list')) {
            abort(403, 'Unauthorized action.');
        }
        $company =DB::table('master_company')
                        ->select('company_alias')
                        ->whereNotNull('company_alias')
                        ->get();
        $section =[
            'car','bike','cv','gcv'
        ];
        if(request()->company_name != null && request()->section != null )
        {
            try{
                $table_name = request()->section.'_addon_configuration';
                $addon =DB::table($table_name)
                        ->select('id','addon',request()->company_name .' as addon_age')
                        ->orderBy('id','asc')
                        ->get();
                $company_name = request()->company_name;
                $section = [request()->section];     

                return view(('addon_config.index'),compact('addon','company','company_name','section'));
            }catch(\Exception $e)
            {
                return redirect()->back()->with([
                    'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                    'class' => 'danger',
                ]);
            }
        }
         return view(('addon_config.index'),compact('company','section'));
       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
       
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
       
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
      
        $table_name = request()->type.'_addon_configuration';
        $addon =DB::table($table_name)
                ->select('id','addon',request()->company .' as addon_age')
                ->orderBy('id','asc')
                ->get();
        $company = request()->company;
        $section = request()->type;
        return compact('addon','company','section');
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
        try {
            if((request()->muliupdate == 'true'))
            {
            $table_name =request()->section.'_addon_configuration';
                foreach(request()->age as $key=>$val)
                {
                    DB::table($table_name)
                    ->where('id',$key)
                    ->update([request()->company_name=>$val]);
                }
                return redirect()->back()->with([
                    'status' => 'updated Successfully',
                    'class' => 'success',
                ]);
            }
            $table_name =request()->type.'_addon_configuration';
            DB::table($table_name)
                    ->where('id',request()->id)
                    ->update([request()->company=>request()->age]);
            return redirect()->back()->with([
                'status' => 'updated Successfully',
                'class' => 'success',
            ]);
        } catch (\Exception $e) 
        {
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
}
