<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\DiscountDomain;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class DiscountDomainController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $discountDomains = DiscountDomain::all();
        return view('discount_domain.index', compact('discountDomains'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('discount_domain.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validateData = [
            'domain' => 'nullable',
            'file' => 'file',
        ];
        $validator = Validator::make($request->all(), $validateData);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()]);
        }
        try {
            if (!is_null($validateData['domain'])) {
                DiscountDomain::updateOrCreate(['domain' => $validateData['domain']], ['domain' => $validateData['domain']]);
            } else {
                $insert_data = \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\DisountDomainImport, $request->file('file'));
            }
            return redirect()->route('admin.discount-domain.index')->with([
                'status' => 'Domains Created Successfully....',
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
     * Display the specified resource.
     *
     * @param  \App\Models\DiscountDomain  $discountDomain
     * @return \Illuminate\Http\Response
     */
    public function show(DiscountDomain $discountDomain)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\DiscountDomain  $discountDomain
     * @return \Illuminate\Http\Response
     */
    public function edit(DiscountDomain $discountDomain)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\DiscountDomain  $discountDomain
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, DiscountDomain $discountDomain)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\DiscountDomain  $discountDomain
     * @return \Illuminate\Http\Response
     */
    public function destroy(DiscountDomain $discountDomain)
    {
        //
    }

    public function sampleFile(Request $request)
    {
         return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\DisountDomainSampleFileExport, 'Domain Sample.xls');
    }
}
