<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use App\Models\RenewalDataApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RenewalDataLogController extends Controller
{
    /**
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function index(Request $request)
    {
        if (!auth()->user()->can('renewal_data_api_logs.list')) {
            return abort(403, 'Unauthorized action.');
        }
        $data = [];
        if (!empty($request->query())) {
            $data = DB::table('renewal_data_api as r')
            ->join('user_product_journey as j',
            'j.user_product_journey_id', '=', 'r.user_product_journey_id')
            ->selectRaw("CONCAT(DATE_FORMAT(j.created_on, '%Y%m%d' ),
            LPAD(j.user_product_journey_id, 8, 0)) as traceId, r.id,
            r.registration_no, r.policy_number, r.created_at, r.updated_at")
            ->orderBy('id', 'DESC');
            if (is_numeric($request->TraceRcNumber)) {
                $data = $data->where('r.user_product_journey_id', ltrim(acceptBothEncryptDecryptTraceId($request->TraceRcNumber), '0'));
            } else {
                $data = $data->where('r.registration_no', $request->TraceRcNumber);
            }
            $data = $data->where('mmv_source', 'RENEWAL API')->paginate($request->paginate);
        }
        return view('admin_lte.renewal-data-logs.logs', compact('data'));
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
        if (!auth()->user()->can('renewal_data_api_logs.list')) {
            return abort(403, 'Unauthorized action.');
        }
        $log = DB::table('renewal_data_api as r')
        ->join('user_product_journey as j',
        'j.user_product_journey_id', '=', 'r.user_product_journey_id')
        ->selectRaw("CONCAT(DATE_FORMAT(j.created_on, '%Y%m%d' ),
        LPAD(j.user_product_journey_id, 8, 0)) as traceId, r.*")
        ->where('r.id', $id)
        ->where('r.mmv_source', 'RENEWAL API')
        ->first();

        if ($request->view == 'download') {
            $text = "RC Number : ".$log->registration_no;
            $text .= "\n\n\nTrace ID : " . ($log->traceId ?? '');
            $text .= "\n\n\nRequest URL : " . ($log->url ?? '');
            $text .= "\n\n\nRequest : " . ($log->api_request ?? '');
            $text .= "\n\n\nResponse : " . ($log->api_response ?? '');

            $fileName = \Illuminate\Support\Str::lower(now()->format('Y-m-d H:i:s').'-'.$log->traceId.'-'.$log->registration_no.'-Renewal-Data-Log.txt');
            return response($text, 200, [
                "Content-Type" => "text/plain",
                'Content-Disposition' => sprintf('attachment; filename="' . $fileName .  '"')
            ]);
        }
        return view('admin_lte.renewal-data-logs.show', compact('log'));
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
