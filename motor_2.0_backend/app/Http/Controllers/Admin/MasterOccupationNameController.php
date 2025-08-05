<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MasterOccupationName;

class MasterOccupationNameController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('master_occuption_name.list')) {
            abort(403, 'Unauthorized action.');
        }

        $occuption = MasterOccupationName::all();

        return view('occuption_name.index', compact('occuption'));
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

        $request->validate([
            'occupation_name'=>'required'
        ]);
       
        $occuption = MasterOccupationName::where([['occupation_name',$request->occupation_name]])->first();
        if ($occuption != null) {
            return redirect()->route('admin.master-occupation-name.index')->with([
                'status' => 'Already pesent please update it....! ',
                'class' => 'danger',
                ]);
        }else{
            try{
                MasterOccupationName::create($request->all());

                return redirect()->route('admin.master-occupation-name.index')->with([
                    'status' => 'Occuption name Added Successfully..!',
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
            $occuption = MasterOccupationName::find($request->id);
            $occuption -> occupation_name = $request -> occupation_name;
            $occuption -> save();

            
            return redirect()->route('admin.master-occupation-name.index')->with([
                'status' => 'Occuption name Updated Successfully..!',
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
            MasterOccupationName::destroy($id);

            return redirect()->route('admin.master-occupation-name.index')->with([
                'status' => 'Occuption name Deleted..!',
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
