<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\WebserviceRequestResponseDataOptionList;


class CkycRedirectionLogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!auth()->user()->can('ckyc_redirection_logs.list')) {
            abort(403, 'Unauthorized action.');
        }
        $logs = [];
        $headers = [];
        $ckycUrl = config('constants.CKYC_VERIFICATIONS_URL') . '/api/v1/redirection-logs';

        if (config('IS_CKYC_WRAPPER_TOKEN_ENABLED') == 'Y') {
            $token = httpRequest('ckyc-wrapper-token', [
                'api_endpoint' => $ckycUrl
            ], save: false)['response'];
            $headers['validation'] = $token['token'];
        }

        if (!empty($request->enquiryId)) {
            $enquiryId  = acceptBothEncryptDecryptTraceId(request()->enquiryId);
            $response = httpRequestNormal($ckycUrl, 'GET', array_filter([
                "trace_id" => customEncrypt(ltrim($enquiryId, '0')),
                "section" => 'motor',
                "company_alias" => $request->company,
                'tenant_id' => config('constants.CKYC_TENANT_ID')
            ]), [], $headers, [], false, false, true);
            $log = $response["response"];
            foreach ($log as $key => $value) {
                array_push($logs, $value);
            }
            return view('admin_lte.ckyc-redirection-logs.index', compact('logs'));
        } else {
            $response = httpRequestNormal($ckycUrl, 'GET', array_filter([
                "section" => 'motor',
                'tenant_id' => config('constants.CKYC_TENANT_ID')
            ]), [], $headers, [], false, false, true);
            if($response['status'] != false){
            $log = $response["response"];
            foreach ($log as $key => $value) {
                array_push($logs, $value);
            }
            }
            else{
                $logs = "Error getting data";
            }
            return view('admin_lte.ckyc-redirection-logs.index', compact('logs'));
        }
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
        $request_headers = json_encode($request_headers, JSON_UNESCAPED_SLASHES);
        $response_headers = json_encode($response_headers, JSON_UNESCAPED_SLASHES);
        return view('admin_lte.ckyc-redirection-logs.show', compact('log', 'request_headers', 'response_headers'));
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
