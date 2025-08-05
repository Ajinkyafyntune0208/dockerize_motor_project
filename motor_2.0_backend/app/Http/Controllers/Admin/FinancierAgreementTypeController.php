<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FinancierAgreementType;
use App\Models\MasterCompany;
use Illuminate\Support\Facades\Validator;
class FinancierAgreementTypeController extends Controller
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

        $occuption = FinancierAgreementType::all();
        $company = MasterCompany::select('company_alias')->orderBy('company_alias')->get();

        return view('financier_agreement_type.index', compact('occuption','company'));
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

        $validateData = [
            'code'=>'required',
            'name'=>'required',
            'company_alias'=>'required'
        ];
        $validator = Validator::make($request->all(), $validateData);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()]);
        }
        $occuption = FinancierAgreementType::where([['company_alias',$request->company_alias],['name', $request->name]])->first();
        if ($occuption != null) {
            return redirect()->route('admin.financier-agreement-type.index')->with([
                'status' => 'Already pesent please update it....! ',
                'class' => 'danger',
                ]);
        }else{
            try{
                FinancierAgreementType::create($request->all());

                return redirect()->route('admin.financier-agreement-type.index')->with([
                    'status' => 'Financier Agreement Added Successfully..!',
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
            $occuption = FinancierAgreementType::find($request->id);
            $occuption -> code = $request -> code;
            $occuption -> name = $request -> name;
            $occuption -> company_alias = $request -> company_alias;
        
            $occuption -> save();

            
            return redirect()->route('admin.financier-agreement-type.index')->with([
                'status' => 'Financier Agreement Updated Successfully..!',
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
            FinancierAgreementType::destroy($id);

            return redirect()->route('admin.financier-agreement-type.index')->with([
                'status' => 'Financier Agreement Deleted..!',
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
