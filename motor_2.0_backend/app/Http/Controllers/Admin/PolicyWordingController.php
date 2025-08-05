<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterPolicy;
use App\Models\MasterPremiumType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PolicyWordingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('policy_wording.list')) {
            return abort(403, 'Unauthorized action.');
        }
         /* $master_policies = MasterPolicy::with('master_product')
        ->join('master_company','master_company.company_id','=','insurance_company_id')
        ->join('master_product_sub_type','master_product_sub_type.product_sub_type_id','=','master_policy.product_sub_type_id')
        ->where('master_policy.status', 'Active')
        ->where('master_product_sub_type.status', 'Active')
        ->get();  */
        $files =  \Illuminate\Support\Facades\Storage::allFiles('policy_wordings');
        //return view('policy_wording.index', compact('master_policies'));
        return view('policy_wording.index2', compact('files'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $master_policies = MasterPolicy::with('master_product')
        ->join('master_company','master_company.company_id','=','insurance_company_id')
        ->join('master_product_sub_type','master_product_sub_type.product_sub_type_id','=','master_policy.product_sub_type_id')
        ->where('master_policy.status', 'Active')
        ->where('master_product_sub_type.status', 'Active')
        ->get();

        $master_premium_types = MasterPremiumType::all(['premium_type', 'premium_type_code']);
        return view(('policy_wording.create'),compact('master_policies', 'master_premium_types'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('policy_wording.edit')) {
            return abort(403, 'Unauthorized action.');
        }
        if(isset($request->file_name))
        {
            try {
                if (Storage::exists('policy_wordings/'.strtolower($request->file_name))) 
                    Storage::delete('policy_wordings/'. strtolower($request->file_name));
                    $request->file('file')->storeAs('policy_wordings',strtolower($request->file_name));
                    return redirect()->route('admin.policy-wording.index')->with([
                        'status' => 'Policy Wording Updated Successfully...!',
                        'class' => 'success',
                    ]);
                
            } catch (\Exception $e) {
                return redirect()->back()->with([
                    'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                    'class' => 'danger',
                ]);
            }
        }
        
        try {
            
            $file_name = implode('-', [
                $request->company_name,
                $request->section,
                $request->business_type,
                $request->policy_type,
            ]). '.pdf';
            $request->file('file')->storeAs('policy_wordings', strtolower($file_name));
            return redirect()->route('admin.policy-wording.index')->with([
                'status' => 'Policy Wording Created Successfully...!',
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
        
        try {dd(request()->file('file'));
                      //\Illuminate\Support\Facades\Storage::delete('policy_wordings/'. request()->file_name);
                     
                      request()->file('policy_wording')->storeAs('policy_wordings', request()->file_name);
                      return redirect()->route('admin.policy-wording.index')->with([
                        'status' => 'Policy Wording Updated Successfully...!',
                        'class' => 'success',
                    ]);
        }catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
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
        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //return request()->file;
        if (!auth()->user()->can('policy_wording.delete')) {
            return abort(403, 'Unauthorized action.');
        }
        \Illuminate\Support\Facades\Storage::delete('policy_wordings/'. request()->file . '.pdf');
        return redirect()->back()->with([
            'status' => 'Policy Wording Deleted Successfully with Policy ID '.request()->file .'..!',
            'class' => 'success',
        ]);
    }
}
