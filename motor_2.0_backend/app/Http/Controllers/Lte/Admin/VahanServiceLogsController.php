<?php

namespace App\Http\Controllers\Lte\Admin;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\VahanServiceLogs;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class VahanServiceLogsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!auth()->user()->can('vahan_service_logs.list')) {
            return abort(403, 'Unauthorized action.');
        }

        $vahan_service_logs = [];
        try {
            if(!empty($request->type === "enquiryid")){
                if ($request->transaction_type == 'proposal') {
                    $vahan_service_logs = VahanServiceLogs::when(!empty($request->transaction_type), function ($query) {
                        $query->where('stage', request()->transaction_type);
                    });
                } else if ($request->transaction_type == 'quote') {
                    $vahan_service_logs = VahanServiceLogs::when(!empty($request->transaction_type), function ($query) {
                        $query->where('stage', request()->transaction_type);
                    });
                } else {
                    $vahan_service_logs = VahanServiceLogs::when(!empty($request->transaction_type), function ($query) {
                        $query->where('stage', request()->transaction_type);
                    });
                }

                $enquiryId = acceptBothEncryptDecryptTraceId($request->userInput);
                if ($request->userInput != null || $request->transaction_type || ($request->from_date != null && $request->to_date != null)) {
                    $vahan_service_logs = VahanServiceLogs::when(!empty(ltrim($enquiryId,'0')), function ($query) use ($request,$enquiryId) {
                            $query->where('enquiry_id', ltrim($enquiryId,'0'));
                        })->when(!empty($request->from_date) && !empty($request->to_date), function ($query) use ($request) {
                            $query->whereBetween('created_at', [
                                Carbon::parse($request->from_date)->startOfDay(),
                                Carbon::parse($request->to_date)->endOfDay(),
                            ]);
                        })->when(!empty($request->transaction_type), function ($query) {
                            $query->where('stage', request()->transaction_type);
                        })->select('id', 'enquiry_id', 'vehicle_reg_no', 'stage', DB::raw('LEFT(`request`, 50) as request'), DB::raw('LEFT(`response`, 50) as response'), 'status', 'created_at')->orderBy('id', 'DESC')->paginate(50)->withQueryString();
                }
            }
            $userInput = str_replace('-', '', $request->userInput);
            if($request->type === 'rcNumber'){
                if ($request->userInput != null || $request->transaction_type || ($request->from_date != null && $request->to_date != null)) {
                    $vahan_service_logs = VahanServiceLogs::when(!empty($userInput), function ($query) use ($userInput) {
                            $query->whereRaw("REPLACE(vehicle_reg_no,'-','') = ? ", $userInput);
                        })->when(!empty($request->from_date) && !empty($request->to_date), function ($query) use ($request) {
                            $query->whereBetween('created_at', [
                                Carbon::parse($request->from_date)->startOfDay(),
                                Carbon::parse($request->to_date)->endOfDay(),
                            ]);
                        })->when(!empty($request->transaction_type), function ($query) {
                            $query->where('stage', request()->transaction_type);
                        })->select('id', 'enquiry_id', 'vehicle_reg_no', 'stage', DB::raw('LEFT(`request`, 50) as request'), DB::raw('LEFT(`response`, 50) as response'), 'status', 'created_at')->orderBy('id', 'DESC')->orderBy('id', 'DESC')->paginate(50)->withQueryString();
                }
            }
        } catch (\Exception $e) {
                // return $e;
                // return abort($e->getMessage(), 500);
                return redirect()->back()->with('error', $e->getMessage());
            }
        
        return view('admin_lte.vahan_service_logs.index', compact('vahan_service_logs'));
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
    public function show($id, Request $request)
    {
        if (!auth()->user()->can('vahan_service_logs.list')) {
            return abort(403, 'Unauthorized action.');
        }

        $log = VahanServiceLogs::find($id);
        $action = request()->query('action') ?? "";

        if ($action == 'download') {
            $text = "Vehicle Registration Number : " . $log->vehicle_reg_no ?? '';
            $text .= "\n\n\nRequest : " . $log->request ?? '';
            $text .= "\n\n\nResponse : " . $log->response ?? '';
            $text .= "\n\n\nCreated At : " . $log->created_at ?? '';
           
            $file_name = \Illuminate\Support\Str::lower(now()->format('Y-m-d H:i:s') . '-' . "Vahan Service Logs".  '.txt');

            return response($text, 200, [
                "Content-Type" => "text/plain",
                'Content-Disposition' => sprintf('attachment; filename="' . $file_name .  '"')
            ]);
        } 
        return view('admin_lte.vahan_service_logs.show', compact('log'));
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
