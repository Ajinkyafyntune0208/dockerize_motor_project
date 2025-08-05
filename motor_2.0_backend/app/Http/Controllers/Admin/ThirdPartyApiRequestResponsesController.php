<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ThirdPartyApiRequestResponse;
use App\Models\ThirdPartySettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ThirdPartyApiRequestResponsesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!auth()->user()->can('third_party_api.list')) {
            abort(403, 'Unauthorized action.');
        }

        $third_party_request_responses = [];
        if ($request->name != null || $request->url != null || ($request->from != null && $request->to != null)) {
            $third_party_request_responses = ThirdPartyApiRequestResponse::when(!empty($request->name), function ($query) use ($request) {
                    $query->where('name', $request->name);
                })->when(!empty($request->url), function ($query) use ($request) {
                    $query->where('url', 'LIKE', '%' . $request->url . '%');
                })->when(!empty($request->from) && !empty($request->to), function ($query) use ($request) {
                    $query->whereBetween('created_at', [
                        Carbon::parse($request->from)->startOfDay(),
                        Carbon::parse($request->to)->endOfDay(),
                    ]);
                })->orderBy('id', 'DESC');
                if(!$request->onChamge){
                    $third_party_request_responses = $third_party_request_responses->paginate(50);
                }else{
                    $third_party_request_responses = $third_party_request_responses->limit(100)->paginate(100);
                }
        }
      
        $third_party_names = ThirdPartySettings::all('name');
        return view('third_party_api_request_responses.index', compact('third_party_request_responses', 'third_party_names'));
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
        $tp = ThirdPartyApiRequestResponse::findOrFail($id);
        
        $action = request()->query('action') ?? "";

        if ($action == 'download') {
            $text = "Name : " . $tp->name ?? '';
            $text .= "\n\n\nUrl : " . $tp->url ?? '';
            $text .= "\n\n\nRequest : " . json_encode($tp->request, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) ?? '';
            $text .= "\n\n\nResponse : " . json_encode($tp->response, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) ?? '';
            $text .= "\n\n\nHeaders. : " . json_encode($tp->headers, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) ?? '';
            $text .= "\n\n\nResponse Headers. : " . json_encode($tp->response_headers, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) ?? '';
            $text .= "\n\n\nOptions : " . json_encode($tp->options, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) ?? '';
            $text .= "\n\n\nResponse Time : " . json_encode($tp->response_time, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) ?? '';
            $text .= "\n\n\nHttp Status : "  . json_encode($tp->http_status, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) ?? '';
            $text .= "\n\n\nCreated At : " . $tp->created_at ?? '';
           
            $file_name = \Illuminate\Support\Str::lower(now()->format('Y-m-d H:i:s') . '-' . $tp->name . '-' . $tp->id .  '.txt');

            return response($text, 200, [
                "Content-Type" => "text/plain",
                'Content-Disposition' => sprintf('attachment; filename="' . $file_name .  '"')
            ]);
        } 

        return view('third_party_api_request_responses.show',['tp'=> $tp]);

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
