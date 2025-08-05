<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SyncBrokerageLogs;
use Carbon\Carbon;

class BrokerageLogsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!auth()->user()->can('sync_brokerage_logs.list')) {
            abort(403, 'Unauthorized action.');
        }
        $reports = [];
        if (!empty($request->to) && !empty($request->from)) {
            
            $filters = [
                ['created_at','>=', Carbon::parse($request->from)->startOfDay()],
                ['created_at','<=', Carbon::parse($request->to)->endOfDay()],
            ];
            if (!empty($request->configid)) {
                $filters[] = ['retrospective_conf_id', '=', $request->configid];
            }
            if (!empty($request->enquiryId)) {
                $enquiryId = customDecrypt($request->enquiryId);
                $filters[] = ['user_product_journey_id', '=', $enquiryId ];
            }
            $reports = SyncBrokerageLogs::where($filters)->get();
        }
        return view('admin_lte.syncBrokerage.index', compact('reports'));
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
        $report = SyncBrokerageLogs::find($id);
        return view('admin_lte/syncBrokerage.view', compact('report'));
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
