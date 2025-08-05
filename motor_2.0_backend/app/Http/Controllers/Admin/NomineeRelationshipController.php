<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NomineeRelationship;
use App\Models\MasterCompany;
use DB;
use Illuminate\Support\Facades\Validator;
class NomineeRelationshipController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('user.list')) {
            abort(403, 'Unauthorized action.');
        }

        $occuption = NomineeRelationship::all();
        $company = MasterCompany::select('company_alias')->orderBy('company_alias')->get();

        return view('nominee_relation_ship.index', compact('occuption','company'));
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

        $rules = [
            'relation'=>'required|string|max:255',
            'relation_code'=>'required|string|max:255',
            'company_alias'=>'required|string|max:255'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }
        $occuption = NomineeRelationship::where([['company_alias',$request->company_alias],['relation', $request->relation]])->first();
        if ($occuption != null) {
            return redirect()->route('admin.nominee-relation-ship.index')->with([
                'status' => 'Already pesent please update it....! ',
                'class' => 'danger',
                ]);
        }else{
            try{
                DB::table('nominee_relationship')->insert(array('relation'=>$request->relation,'relation_code'=>$request->relation_code,'company_alias'=>$request->company_alias)
                );

                return redirect()->route('admin.nominee-relation-ship.index')->with([
                    'status' => 'Nominee Relation ship Added Successfully..!',
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
    public function update(Request $request)
    {
        if (!auth()->user()->can('user.list')) {
            abort(403, 'Unauthorized action.');
        }
        $rules = [
            'relation'=>'required|string|max:255',
            'relation_code'=>'required|string|max:255',
            'company_alias'=>'required|string|max:255'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }

        try {

            DB::table('nominee_relationship')
        ->where([['relation',$request->relation1],['relation_code', $request->relation_code1],['company_alias', $request->company_alias1]]) 
        ->limit(1) 
        ->update(array('relation'=>$request->relation,'relation_code'=>$request->relation_code,'company_alias'=>$request->company_alias,));

            
            return redirect()->route('admin.nominee-relation-ship.index')->with([
                'status' => 'Nominee Relation ship Updated Successfully..!',
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
    public function destroy(Request $request, $id)
    {
        if (!auth()->user()->can('user.list')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            NomineeRelationship::where(array('relation'=>$request->relation1,'relation_code'=>$request->relation_code1,'company_alias'=>$request->company_alias1))->delete();

            return redirect()->route('admin.nominee-relation-ship.index')->with([
                'status' => 'Nominee Relation ship Deleted..!',
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
