<?php

namespace App\Http\Controllers\Lte\Admin\Ckyc;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\CkycLogsRequestResponse;
use App\Models\WebServiceRequestResponse;
use App\Models\QuoteServiceRequestResponse;
use App\Models\WebserviceRequestResponseDataOptionList;

class CkycWrapperLogController extends Controller
{   
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!auth()->user()->can('ckyc_wrapper_logs.list')) {
            abort(403, 'Unauthorized action.');
        }
        $dropdown_values = WebserviceRequestResponseDataOptionList::select('company', 'section')->get();
        
        $companies = $dropdown_values->pluck('company')->unique()->toArray();
        $logs = [];

        try{
        if (!empty($request->enquiryId) || !empty($request->company)) {
            $logs = CkycLogsRequestResponse::select('id','company_alias', 'mode', 'endpoint_url','enquiry_id','failure_message','response_time','start_time','end_time')
            ->when(!empty($request->enquiryId), function ($query) {
                $query->where('enquiry_id',ltrim(acceptBothEncryptDecryptTraceId(request()->enquiryId),'0'));
            })
            ->orderBy('id', 'DESC')
            ->get();
        }
        if ($request->company == 'reliance') {
            $request->company = 'reliance_general';
        } else if ($request->company == 'liberty_videocon') {
            $request->company = 'liberty_general';
        }
        }catch(\Exception $e){
            return redirect()->back()->with('error',$e->getMessage());
        }
        return view('admin_lte.ckyc_wrapper_logs.index', compact('logs', 'companies'));
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
        if (!auth()->user()->can('ckyc_wrapper_logs.show')) {
            abort(403, 'Unauthorized action.');
        }
        $log = CkycLogsRequestResponse::find($id);
        return view('admin_lte.ckyc_wrapper_logs.show', compact('log'));
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
    public function getLogs(Request $request, $id)
    {
        $log = CkycLogsRequestResponse::select('id','enquiry_id','mode','company_alias','failure_message','response_time','start_time','end_time', 'endpoint_url','headers','request','response')->find($id);
        
        $text = "Trace ID : " . (isset($log->enquiry_id) ? customEncrypt($log->enquiry_id) : '');
        foreach ($log->getAttributes() as $key => $value) {
            if($key == 'id') continue;
            if($key == 'enquiry_id') continue;

            if(in_array($key, ['request', 'response', 'endpoint_url'])) {
                $text .= "\n\n".$key." : \n" . ($value ?? '');
            } else {
                $text .= "\n\n".$key." : " . ($value ?? '');
            }
        }
        $file_name = \Illuminate\Support\Str::lower(now()->format('Y-m-d H:i:s') . '-' . $log->company_alias . '-' . $log->mode . '-' . $log->enquiry_id . '.txt');
        return response($text, 200, [
            "Content-Type" => "text/plain",
            'Content-Disposition' => sprintf('attachment; filename="' . $file_name .  '"')
        ]);
    }
}
