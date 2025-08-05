<?php

namespace App\Http\Controllers\Admin\OnePay;

use Illuminate\Http\Request;
use App\Models\RenewalDataApi;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\OnePay\OnePayController;

class TransactionLogController extends Controller
{
    /**
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function index(Request $request)
    {
        if (!auth()->user()->can('onepay_log.list')) {
            abort(403, 'Unauthorized action.');
        }
        $logs = [];
        if(!empty($request->enquiryId)) {
            $onepay = OnePayController::paymentStatusCheck(new Request(['enquiryId' => $request->enquiryId]));
            $data = $onepay->getOriginalContent();
            // dd($data);
            $logs = $data['data'];
        }
        // dd('raju');
        return view('onepay.index', compact('logs'));

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
    }
    
    /**
    * Display the specified resource.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function show(Request $request, $id)
    {
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
