<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GramcoverPostDataApi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PushApiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('push_api.list')) {
            abort(403, 'Unauthorized action.');
        }

        $enquiryId = acceptBothEncryptDecryptTraceId(request()->enquiryId);
        $gramcover_data = [];
        if (!empty(request()->from && request()->to) || request()->enquiryId) {
            $gramcover_data = GramcoverPostDataApi::when(!empty(request()->from && request()->to || request()->enquiryId), function ($query) use ($enquiryId) {
                $query->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime(request()->from)))
                    ->where('created_at', '<', date('Y-m-d 23:59:59', strtotime(request()->to)))
                    ->where('user_product_journey_id',ltrim($enquiryId,'0'));
            });
            $gramcover_data = $gramcover_data->orderBy('id', 'desc');
            if (request()->type == 'excel'){
                $gramcover_data = $gramcover_data->select('id', 'user_product_journey_id', 'token',/* 'request', 'response', */ 'status', 'created_at')->get();
            }
            else{
                $gramcover_data = $gramcover_data->select('id', 'user_product_journey_id', 'status', 'created_at')->paginate(100)->withQueryString();
            }
        }
        if (request()->type == 'excel') {
            $records[] = [
                'Enquiry Id',
                "Status",
                'Token',
                'View Request Response',
                // 'Response',
                'Date',
            ];
            foreach ($gramcover_data as $key => $value) {
                $records[] = [
                    $value->user_product_journey_id,
                    $value->status,
                    $value->token,
                    route('admin.push-api.show', $value),
                    // json_encode($value->request, JSON_PRETTY_PRINT),
                    // json_encode($value->response, JSON_PRETTY_PRINT),
                    Carbon::parse($value->created_at)->format("d-m-y H:i:s A")
                ];
            }
            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\DataExport($records), 'Gramcover Data Push Records.xls');
        }
        return view('pushapi_data.index', compact('gramcover_data'));
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
        $data = GramcoverPostDataApi::find($id);
        return view('pushapi_data.show', compact('data'));
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

    public function viewRequestResponse(Request $request, $id)
    {
        $data = GramcoverPostDataApi::find($id);
        $html = "<b>User Product Journey ID:</b> {$data->user_product_journey_id}<br><br>";
        $html .= "<b>Token:</b> {$data->token}<br><br>";
        $html .= "<b>Request:</b> <pre>". json_encode($data->request, JSON_PRETTY_PRINT). "</pre><br><br>";
        $html .= "<b>Response:</b> <pre>". json_encode($data->request, JSON_PRETTY_PRINT). "</pre><br><br>";
        $html .= "<b>Status:</b> {$data->status}<br><br>";
        $html .= "<b>Created At:</b> {$data->created_at}<br><br>";
        $html .= "<b>Updated At:</b> {$data->updated_at}<br><br>";
        return $html;
    }
}
