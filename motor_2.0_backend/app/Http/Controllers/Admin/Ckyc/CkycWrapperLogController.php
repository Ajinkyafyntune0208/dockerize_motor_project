<?php

namespace App\Http\Controllers\Admin\Ckyc;

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
        if (!auth()->user()->can('ckyc_wrapper_log.list')) {
            abort(403, 'Unauthorized action.');
        }
        $dropdown_values = WebserviceRequestResponseDataOptionList::select('company', 'section')->get();
        
        $companies = $dropdown_values->pluck('company')->unique()->toArray();
        $logs = [];

        if(config('enquiry_id_encryption') == 'Y'){
            try {
                $enquiryId = null;
                if(strlen($request->enquiryId) == 16 && config('enquiry_id_encryption') == 'Y' && (integer)$request->enquiryId){
                    $new_enquiryId = \Illuminate\Support\Str::substr($request->enquiryId, 8);
                    $enquiryId = customDecrypt(customEncrypt($new_enquiryId));
                } else if ($request->enquiryId) {
                    $enquiryId = customDecrypt($request->enquiryId);
                }
            } catch (\Throwable $th) {
                return redirect()->back()->withInput()->with('error', "Invalid enquiry id");
            }
        }else{
            if(is_numeric($request->enquiryId)){
                $enquiryId = customDecrypt($request->enquiryId);
            }
            else if($request->enquiryId){
                $enquiryId = enquiryIdDecryption($request->enquiryId);
            }
        }

    try{
        if (!empty($request->enquiryId) || !empty($request->company)) {
            $logs = CkycLogsRequestResponse::select('id','company_alias', 'mode', 'endpoint_url','enquiry_id','failure_message','response_time','start_time','end_time')
            ->Where('enquiry_id',ltrim($enquiryId,'0'))
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
        return view('ckyc_wrapper_logs.index', compact('logs', 'companies'));
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
        if (!auth()->user()->can('log.show')) {
            abort(403, 'Unauthorized action.');
        }
        $log = CkycLogsRequestResponse::find($id);
        return view('ckyc_wrapper_logs.show', compact('log'));
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
