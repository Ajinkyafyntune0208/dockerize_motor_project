<?php

namespace App\Http\Controllers\Admin;

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
        if (!auth()->user()->can('ckyc_log.list')) {
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
            $ckycVerificationUrl = config('constants.CKYC_VERIFICATIONS_URL') . '/api/v1/http-logs';

            if (config('IS_CKYC_WRAPPER_TOKEN_ENABLED') == 'Y') {
                $token = httpRequest('ckyc-wrapper-token', [
                    'api_endpoint' => $ckycVerificationUrl
                ], save:false)['response'];
                $headers['validation'] = $token['token'];
            }

            if(config('enquiry_id_encryption') == 'Y'){
                try {
                    $enquiryId = null;
                    if(strlen(request()->enquiryId) == 16 && config('enquiry_id_encryption') == 'Y' && (integer)request()->enquiryId){
                        $new_enquiryId = \Illuminate\Support\Str::substr(request()->enquiryId, 8);
                        $enquiryId = customDecrypt(customEncrypt($new_enquiryId));
                    } else if (request()->enquiryId) {
                        $enquiryId = customDecrypt(request()->enquiryId);
                    }
                } catch (\Throwable $th) {
                    return redirect()->back()->withInput()->with('error', "Invalid enquiry id");
                }
            }else{
                if(is_numeric(request()->enquiryId)){
                    $enquiryId = customDecrypt(request()->enquiryId);
                }
                else if(request()->enquiryId){
                    $enquiryId = enquiryIdDecryption(request()->enquiryId);
                }
            } 

            $response = httpRequestNormal($ckycVerificationUrl, 'GET', array_filter([
                "trace_id" => customEncrypt(ltrim($enquiryId, '0')),
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
        return view('ckyc_http_logs.index', compact('logs', 'companies'));
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
        $headers = [];
        $ckycUrl = config('constants.CKYC_VERIFICATIONS_URL') . "/http-logs/" . $id . '/' . $table_name;
        
        if (config('IS_CKYC_WRAPPER_TOKEN_ENABLED') == 'Y') {
            $token = httpRequest('ckyc-wrapper-token', [
                'api_endpoint' => $ckycUrl
            ], save:false)['response'];
            $headers['validation'] = $token['token'];
        }

        $log = httpRequestNormal($ckycUrl, 'GET', [], [], $headers, [], false, false, true)['response'];

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
        return view('ckyc_http_logs.show', compact('log', 'request_headers', 'response_headers'));
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
