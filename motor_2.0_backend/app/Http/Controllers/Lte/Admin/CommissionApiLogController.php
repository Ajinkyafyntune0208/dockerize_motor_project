<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionApiLog;
use Illuminate\Http\Request;

class CommissionApiLogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!auth()->user()->can('commission_api_logs.list')) {
            abort(403, 'Unauthorized action.');
        }
        $logs = [];

        if (!empty($request->enquiryId)) {
            $enquiryId  = acceptBothEncryptDecryptTraceId($request->enquiryId);
            $logs = CommissionApiLog::where('user_product_journey_id', $enquiryId)
            ->select('id', 'url', 'request', 'response', 'type', 'transaction_type', 'updated_at as date')
            ->orderBy('updated_at', 'desc')
            ->get();
        }

        return view('admin_lte.commission_api_logs.index', compact('logs'));
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
    public function show($id)
    {
        $log = CommissionApiLog::find($id);

        if (empty($log)) {
            abort(404);
        }

        return view('admin_lte.commission_api_logs.show', [
            'log' => $log,
            'traceId' => customEncrypt($log->user_product_journey_id)
        ]);
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
