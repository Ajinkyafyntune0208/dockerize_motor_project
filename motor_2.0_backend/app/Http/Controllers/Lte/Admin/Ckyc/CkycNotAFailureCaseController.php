<?php

namespace App\Http\Controllers\Lte\Admin\Ckyc;

use App\Http\Controllers\Controller;
use App\Models\CKYCNotAFailureCases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;

class CkycNotAFailureCaseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request)
    {
        if (!auth()->user()->can('ckyc_not_a_failure_cases.list')) {
            return abort(403, 'Unauthorized action.');
        }
        
        $CKYCNotAFailureCasess = CKYCNotAFailureCases::when($request->has('search') && !empty($request->search), function ($query) use ($request) {
            $query->where('message', 'like', '%' . $request->search . '%');
        })->orderBy('id', 'DESC')->paginate($request->per_page ?? 10);

        return view('admin_lte.ckyc_not_a_failure_cases.index', compact('CKYCNotAFailureCasess'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin_lte.ckyc_not_a_failure_cases.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // $validatedData = $request->validate([
        //     'type' => 'required|string',
        //     'message' => 'required|string',
        //     'active' => 'required|integer',
        // ]);
        $rules = [
            'type' => 'required|string',
            'message' => 'required|string',
            'active' => 'required|integer|in:1,0',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }
            
        try {
            CKYCNotAFailureCases::create($request->all());
            return redirect()->route('admin.ckyc_not_a_failure_cases.index')->with([
                'status' => 'Rule Created for section',
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
     * @param  \App\Models\CKYCNotAFailureCases  $CKYCNotAFailureCases
     * @return \Illuminate\Http\Response
     */
    public function show(CKYCNotAFailureCases $CKYCNotAFailureCases)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CKYCNotAFailureCases  $CKYCNotAFailureCases
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $CKYCNotAFailureCasess = CKYCNotAFailureCases::where("id", $id)->first();
        return view('admin_lte.ckyc_not_a_failure_cases.edit', compact('CKYCNotAFailureCasess'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\CKYCNotAFailureCases  $CKYCNotAFailureCases
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        // $validatedData = $request->validate([
        //     'type' => 'required|string',
        //     'message' => 'required|string',
        //     'active' => 'required|integer',
        // ]);
        $rules = [
            'type' => 'required|string',
            'message' => 'required|string',
            'active' => 'required|integer|in:1,0',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }
        try {
            CKYCNotAFailureCases::where("id", $id)->first()->update($request->all());
            return redirect()->route('admin.ckyc_not_a_failure_cases.index')->with([
                'status' => 'Rule Updated for section',
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
     * @param  \App\Models\CKYCNotAFailureCases  $CKYCNotAFailureCases
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            // dd($id);
            CKYCNotAFailureCases::where("id", $id)->delete();
            return redirect()->route('admin.ckyc_not_a_failure_cases.index')->with([
                'status' => 'Data Deleted Successfully ..!',
                'class' => 'success',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }

    // public function getCKYCNotAFailureCasess($request)
    // {
    //     return CKYCNotAFailureCases::get();

    // }
}
