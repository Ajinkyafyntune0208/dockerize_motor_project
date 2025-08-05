<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('company.list')) {
            return abort(403,'Unauthorized action.');
        }
        $companies = MasterCompany::orWhereNotNull('company_alias')->get();
        return view('company.index', compact('companies'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!auth()->user()->can('company.create')) {
            return abort(403,'Unauthorized action.');
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
        if (!auth()->user()->can('company.create')) {
            return abort(403,'Unauthorized action.');
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
        if (!auth()->user()->can('company.show')) {
            return abort(403,'Unauthorized action.');
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!auth()->user()->can('company.edit')) {
            return abort(403,'Unauthorized action.');
        }
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
        if (!auth()->user()->can('company.edit')) {
            return abort(403,'Unauthorized action.');
        }
        try {
            $company = MasterCompany::find($id);
            Storage::disk('public_uploads')->delete('uploads/logos/'.$company->company_alias.'.png');
            $file_name = $request->file('logo')->storeAs('uploads/logos',$company->company_alias.'.png', 'public_uploads');
            $file_name = \Illuminate\Support\Str::replace('uploads/logos/','', $file_name);
            
            $company->logo = $file_name;
            $company->save();
            return redirect()->route('admin.company.index')->with([
                'status' => 'Company Logo Updated Successfully..!',
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
        if (!auth()->user()->can('company.delete')) {
            return abort(403,'Unauthorized action.');
        }
        try {
            $company = MasterCompany::find($id);
            Storage::disk('public_uploads')->delete('uploads/logos/'.$company->logo);            
            $company->logo = null;
            $company->save();
            return redirect()->route('admin.company.index')->with([
                'status' => 'Company Logo Deleted Successfully..!',
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
