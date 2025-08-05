<?php

namespace App\Http\Controllers\Lte\Admin;

use Illuminate\Http\Request;
use App\Models\ThirdPartySetting;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ThirdPartySettingsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('third_party_settings.list')) {
            abort(403, 'Unauthorized action.');
        }

        $occuption = ThirdPartySetting::orderBy('id', 'desc')->get();

        return view('admin_lte.third_party_settings.index', compact('occuption'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin_lte.third_party_settings.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('user.list')) {
            abort(403, 'Unauthorized action.');
        }

        $validator = Validator::make($request->all(), [
            'name'=>'required',
            'url'=>'required',
            'method'=>'required',
            'api_headers' => 'nullable|json',
            'body' => 'nullable|json',
            'option' => 'nullable|json'

        ]);
        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }
        $headers = json_decode($request->api_headers, true);
        $body = json_decode($request->body, true);
        $option = json_decode($request->option, true);
        try{
            ThirdPartySetting::create([
                'name' => trim($request->name),
                'url' => trim($request->url),
                'method' => trim($request->method),
                'headers'=> $headers,
                'body'=> $body,
                'options' => $option
            ]);

            return redirect()->route('admin.third_party_settings.index')->with([
                'status' => 'Third Party Settings Added Successfully..!',
                'class' => 'success',
                ]);
        } catch (\Exception $e) {
                return redirect()->back()->with([
                    'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                    'class' => 'danger',
                ]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $configuration = ThirdPartySetting::where('id',$id)->first();
        return view('admin_lte.third_party_settings.edit',['configs'=> $configuration ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ThirdPartySetting $thirdPartySetting)
    {
        if (!auth()->user()->can('user.list')) {
            abort(403, 'Unauthorized action.');
        }
        $validateData =  [
            'name'=>'required',
            'url'=>'required',
            'method'=>'required',
            'api_headers' => 'nullable|json',
            'body' => 'nullable|json',
            'option' => 'nullable|json'
        ];
        $validator = Validator::make($request->all(), $validateData);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }
        $headers = json_decode($request->api_headers, true);
        $body = json_decode($request->body, true);
        $option = json_decode($request->option, true);
        //$thirdPartySetting['body'] =  json_decode($thirdPartySetting['body'],true);
        try {
            $apiRequest = $request->all();
            $data = $apiRequest;
            
            if (!empty($data['body'])) {
                $data['body'] = json_decode($data['body']);
            }

            if (!empty($data['api_headers'])) {
                $data['headers'] = json_decode($data['api_headers']);
            }

            if (!empty($data['option'])) {
                $data['options'] = json_decode($data['option']);
            }
            $request->replace($data);
            $thirdPartySetting->update($request->all());
            return redirect()->route('admin.third_party_settings.index')->with([
                'status' => 'Third Party Settings Updated Successfully..!',
                'class' => 'success',
                ]);

        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!auth()->user()->can('user.list')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            ThirdPartySetting::destroy($id);

            return redirect()->route('admin.third_party_settings.index')->with([
                'status' => 'Third Party Settings Deleted..!',
                'class' => 'success',
                ]);

        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }
}
