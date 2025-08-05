<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use App\Models\RtoPreferredCity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RtoPreferredController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('preferred_rto.list')) {
            abort(403, 'Unauthorized action.');
        }
        $master_cities = \App\Models\MasterCity::all();
        $prefred_cities = \App\Models\RtoPreferredCity::all();
        return view('admin_lte.rto-prefered.index', compact('prefred_cities', 'master_cities'));
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
        $rules = [
            'preferred_city_id.*' => "nullable|integer",
            'city_name.*' => "nullable|distinct",
            'priority.*' => "nullable|distinct|integer"
        ];
        $validator = Validator::make($request->all(),$rules);
        if($validator->fails()){
            return redirect()->back()->withErrors($validator->errors());
        }
        $validateData = $request->only('preferred_city_id','city_name','priority');
        try {
            $datas = [];
            foreach ($validateData['city_name'] as $key => $data) {
                $datas = [
                    'preferred_city_id' => $validateData['preferred_city_id'][$key] ?? '',
                    'city_name' => $validateData['city_name'][$key] ?? '',
                    'priority' => $validateData['priority'][$key] ?? '',
                ];
                if (!empty($datas['city_name']) && !empty($datas['priority'])) {
                    RtoPreferredCity::updateOrCreate(['preferred_city_id' => $datas['preferred_city_id']], $datas);
                }
            }

            return redirect()->back()/* ('admin.master-policy.index') */->with([
                'status' => 'Preferred RTO Updated Successfully..!',
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
     * @param  \App\Models\RtoPreferredCity  $rtoPreferredCity
     * @return \Illuminate\Http\Response
     */
    public function show(RtoPreferredCity $rtoPreferredCity)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\RtoPreferredCity  $rtoPreferredCity
     * @return \Illuminate\Http\Response
     */
    public function edit(RtoPreferredCity $rtoPreferredCity)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\RtoPreferredCity  $rtoPreferredCity
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, RtoPreferredCity $rtoPreferredCity)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\RtoPreferredCity  $rtoPreferredCity
     * @return \Illuminate\Http\Response
     */
    public function destroy(RtoPreferredCity $rto_prefered)
    {
        try {
            $rto_prefered->delete();
            return redirect()->back()/* ('admin.master-policy.index') */->with([
                'status' => 'Preferred RTO Deleted Successfully..!',
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
