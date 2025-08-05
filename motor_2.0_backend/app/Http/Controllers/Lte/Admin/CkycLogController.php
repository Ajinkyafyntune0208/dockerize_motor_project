<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\WebserviceRequestResponseDataOptionList;

class CkycLogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        if (!auth()->user()->can('ckyc_logs.list')) {
            abort(403, 'Unauthorized action.');
        }
        $dropdown_values = WebserviceRequestResponseDataOptionList::select('company', 'section')->get();
        $companies = $dropdown_values->pluck('company')->unique()->toArray();
        $logs = [];


        if (!empty($request->enquiryId) || !empty($request->company)) {

            $request->company = $request->company ?? '' ;
            if ($request->company == 'reliance') {
                $request->company = 'reliance_general';
            } else if ($request->company == 'liberty_videocon') {
                $request->company = 'liberty_general';
            }

            $headers = [];
            $ckycUrl = config('constants.CKYC_VERIFICATIONS_URL') . '/api/v1/http-logs';

            if (config('IS_CKYC_WRAPPER_TOKEN_ENABLED') == 'Y') {
                $token = httpRequest('ckyc-wrapper-token',[
                    'api_endpoint' => $ckycUrl
                ], save:false)['response'];
                
                $headers['validation'] = $token['token'];
            }
            
            $enquiryId  = acceptBothEncryptDecryptTraceId(request()->enquiryId);
            $response = httpRequestNormal($ckycUrl, 'GET', array_filter([
                "trace_id" => customEncrypt(ltrim($enquiryId,'0')),
                "section" => 'motor',
                "company_alias" => $request->company,
                'tenant_id' => config('constants.CKYC_TENANT_ID')
            ]), [], $headers, [], false, false, true);
            $logs = $response["response"];
        }

        if ($request->company == 'reliance_general') {
            $request->company = 'reliance';
        } else if ($request->company == 'liberty_general') {
            $request->company = 'liberty_videocon';
        }
        return view('admin_lte.ckyc-logs.index', compact('logs', 'companies'));
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
    public function show($id, $table_name)
    {
        $log = httpRequestNormal(config('constants.CKYC_VERIFICATIONS_URL') . "/http-logs/" . $id . '/' . $table_name, 'GET', [], [], [], [], false, false, true)['response'];

        if (empty($log)) {
            abort(404);
        }
        $response_headers = $request_headers = [];
        if (isset($log['request_headers']) && is_array($log['request_headers'])) {
            foreach ($log['request_headers'] as $key => $request_header) {
                if (!in_array($key, ['User-Agent', 'Host'])) {
                    if(!(is_array($request_header) && isset($request_header[0]))) {
                        $request_headers[$key] = $request_header;
                    } else {
                        $request_headers[$key] = $request_header[0];
                    }
                }
            }
        } else if(!empty($log['request_headers'])) {
            $request_headers = $log['request_headers'];
        }
        if (isset($log['response_headers']) && is_array($log['response_headers'])) {
            foreach ($log['response_headers'] as $key => $request_header) {
                if (!in_array($key, ['User-Agent', 'Host'])) {
                    $response_headers[$key] = $request_header[0];
                }
            }
        } else if(!empty($log['response_headers'])) {
            $response_headers = $log['response_headers'];
        }

        $request_headers = json_encode($request_headers, JSON_UNESCAPED_SLASHES);
        $response_headers = json_encode($response_headers, JSON_UNESCAPED_SLASHES);
        return view('admin_lte.ckyc-logs.show', compact('log', 'request_headers', 'response_headers'));
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
