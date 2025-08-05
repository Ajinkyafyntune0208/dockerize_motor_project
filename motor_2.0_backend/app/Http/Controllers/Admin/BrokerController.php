<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrokerDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BrokerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $broker_details = BrokerDetail::all();
        return view('broker_details.index',compact('broker_details'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('broker_details.create');
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
            'name' => 'required|string',
            'frontend_url' => 'required|string|url',
            'backend_url' => 'required|string|url',
            'support_email' => 'required|string|email',
            'support_tollfree' => 'required',
            'environment' => 'required|string',
            'status' => 'required|in:active,inactive',
        ];
        $validator = Validator::make($request->all(),$rules);
        if($validator->fails()){
            return back()->withErrors($validator->errors())->withInput();
        }
        try {
            $brokerdata = BrokerDetail::create([
                'name'=>$request->name,
                'frontend_url'=>$request->frontend_url,
                'backend_url'=>$request->backend_url,
                'support_email'=>$request->support_email,
                'support_tollfree'=>$request->support_tollfree,
                'environment'=>$request->environment,
                'status'=>$request->status
            ]);

            return redirect()->route('admin.broker.index')->with([
                'status' => 'Broker Details Created for Name '.$request->name,
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
     * @param  \App\Models\BrokerDetail  $brokerDetail
     * @return \Illuminate\Http\Response
     */
    public function show(BrokerDetail $brokerDetail)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\BrokerDetail  $brokerDetail
     * @return \Illuminate\Http\Response
     */
    public function edit(BrokerDetail $broker)
    {
        return view('broker_details.edit',['broker_details'=> $broker ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\BrokerDetail  $brokerDetail
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, BrokerDetail $broker)
    {

        $rules = [
            'name' => 'required|string',
            'frontend_url' => 'required|string|url',
            'backend_url' => 'required|string|url',
            'support_email' => 'required|string|email',
            'support_tollfree' => 'required',
            'environment' => 'required|string',
            'status' => 'required|in:active,inactive',
        ];
        $validator = Validator::make($request->all(),$rules);
        if($validator->fails()){
            return back()->withErrors($validator->errors())->withInput();
        }
        try {
            $brokerdata = BrokerDetail::where(['id' => $broker->id])->update([
                'name'=>$request->name,
                'frontend_url'=>$request->frontend_url,
                'backend_url'=>$request->backend_url,
                'support_email'=>$request->support_email,
                'support_tollfree'=>$request->support_tollfree,
                'environment'=>$request->environment,
                'status'=>$request->status
            ]);

            return redirect()->route('admin.broker.index')->with([
                'status' => 'Broker Details Updated for Name '.$request->name,
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
     * @param  \App\Models\BrokerDetail  $brokerDetail
     * @return \Illuminate\Http\Response
     */
    public function destroy(BrokerDetail $broker)
    {
//        dd($brokerDetail);
        try {
            $broker->delete();
            return redirect()->route('admin.broker.index')->with([
                'status' => 'Broker Deleted Successfully ..!',
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
