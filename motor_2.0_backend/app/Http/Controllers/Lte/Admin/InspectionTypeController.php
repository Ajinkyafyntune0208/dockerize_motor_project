<?php

namespace App\Http\Controllers\Lte\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\InspectionType;
use App\Models\MasterCompany;
use App\Models\MasterPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InspectionTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!auth()->user()->can('inspection_type.list')) {
            abort(403, 'Unauthorized action.');
        }
        $company_id = MasterPolicy::select('insurance_company_id')->distinct()->whereIn('premium_type_id', [4, 6])->where('status','Active')
            ->get()->toArray();
            $com_id=[];
           foreach($company_id as $key => $value)
           {
                foreach($value as $num=>$data)
                {
                    $com_id[]= $data;
                }
           }
        $data = InspectionType::whereIn('company_id',$com_id)->get();
        return view('admin_lte.inspection_type.index', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $company_id = MasterPolicy::select('insurance_company_id')->distinct()->whereIn('premium_type_id', [4, 6])->where('status', 'Active')->get();
        $data = MasterCompany::select('company_id', 'company_alias')->whereIn('company_id', $company_id)->get();
        return view('admin_lte.inspection_type.create', compact('data'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'company_id'  => 'required',
            'manual_type' => 'in:Y,N',
            'self_type' => 'in:Y,N'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        } else {
            $data = MasterCompany::select('company_alias')->where('company_id', $request->company_id)->first();
            $updated = InspectionType::updateOrCreate(
                [
                    'company_id'    => $request->company_id,
                ],
                [
                    'company_id'    => $request->company_id,
                    'company_name'  => $data->company_alias,
                    'Manual_inspection' => $request->manual_type,
                    'Self_inspection' => $request->self_type
                ],
            );
            if ($updated) {
                return redirect()->route('admin.inspection.index')->with(['status' => 'Inspection Type inserted Successfully.', 'class' => 'success']);
            } else {
                return redirect()->back()->with(['status' => 'Error While inserting Manu.', 'class' => 'danger']);
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
    public function edit(Request $request, $id)
    {
        $data = InspectionType::select('id', 'company_name', 'Manual_inspection', 'Self_inspection')
            ->where('id', base64_decode($id))->get();
        return view('admin_lte.inspection_type.edit', compact('data'));
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
        // dd($request->toArray());
        $rules = [
            'company_name'  => 'required|string',
            'manual_type' => 'in:Y,N',
            'self_type' => 'in:Y,N'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        } else {
            $updated = InspectionType::where('id', $request->id)->update([
                'company_name'  => $request->company_name,
                'Manual_inspection' => $request->manual_type,
                'Self_inspection' => $request->self_type
            ]);
            if ($updated){
                return redirect()->route('admin.inspection.index')->with(['status' => 'Inspection Type Updated Successfully.','class' => 'success']);
            } else {
                return redirect()->back()->withErrors(['status' => 'Error While Updating Manu.'])->withInput();
            }
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
