<?php

namespace App\Http\Controllers\Admin\Ckyc;

use App\Http\Controllers\Controller;
use App\Models\CkycVerificationTypes;
use App\Models\MasterCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CkycVerificationTypesController extends Controller
{
  

    public function index(Request $request)
    {
        //search opereation  and index
        $search = $request['search'] ?? '';
        if($search != ""){
            $validator = Validator::make($request->all(), [
                'search' => 'required|exists:master_company,company_alias'
            ]);
    
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator);
            }
            $ckyc_verification_data = CkycVerificationTypes::where('company_alias', 'like', "%$search%")
            ->orderBy('id', 'DESC')->paginate($request->per_page ?? 10);
           
        }else{
            $ckyc_verification_data = CkycVerificationTypes::orderBy('id', 'DESC')
            ->paginate($request->per_page ?? 10);
        }
        //create
        $data = CkycVerificationTypes::select('company_alias')->get();
        $company_alias = MasterCompany::whereNotIn('company_alias',$data)->get();

        return view('ckyc_verification_types.index',compact('ckyc_verification_data','company_alias'));
    }


    public function create()
    {
        
    }

    public function store(Request $request)
               
    {
        $rules=[
            'company_alias' => 'required|string',
            'mode' => 'required | string',
        ];
        $validator = Validator::make($request->all(), $rules);
       if($validator->fails()){
        return redirect()->back()->withErrors($validator->errors())->withInput();
       }
    try {
        CkycVerificationTypes::create([
            "company_alias" => $request->company_alias,
            "mode" => $request->mode,
        ]);
        return redirect()->route('admin.ckyc_verification_types.index')->with([
            'status' => 'Ckyc Verification Types Added succesfully ',
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
        $request->validate([
            'mode' => 'required',   
        ]);
        $Id = decrypt($request->id);
        try {
            CkycVerificationTypes::find($Id)->update([
                 "mode" => $request->mode,  
            ]);
            
            return redirect()->route('admin.ckyc_verification_types.index')->with([
                'status' => 'Ckyc Verification Types Added succesfully ',
                'class' => 'success',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }

    
    public function destroy($id)
    {
        $Id = decrypt($id);
        try{
        CkycVerificationTypes::find($Id)->delete();
        return redirect()->route('admin.ckyc_verification_types.index')->with([
            'status' => 'Data Deleted Successfully ..!',
            'class' => 'success',
        ]);
        } catch(\Exception $e){
            return redirect()-back()->with([
                'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',]
            );
        }
    }
}
