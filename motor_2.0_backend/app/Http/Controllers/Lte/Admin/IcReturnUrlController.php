<?php

namespace App\Http\Controllers\Lte\Admin;
use App\Http\Controllers\Controller;
use App\Models\MasterCompany;
use Illuminate\Http\Request;
// use App\Models\MasterCompany;


class IcReturnUrlController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $companies = MasterCompany::pluck('company_alias');
        $returnurl = "";
        $categories = ['car','bike','cv'];
        if(!empty($request->category) && !empty($request->company) ){ 
            $category = ($request->category);
            $company = $request->company;
            $returnurl = url('').'/'.($category).'/payment-confirm/'.$company;
            $returnurl = strtolower($returnurl);
        }
        return view('admin_lte.IcReturnURL' , compact('companies', 'categories','returnurl'));
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
        //
        // return 'success';
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
