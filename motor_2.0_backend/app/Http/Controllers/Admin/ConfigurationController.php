<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfigSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ConfigurationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!auth()->user()->can('configuration.list')) {
            return abort(403, 'Unauthorized action.');
        }
        $perPage = $request->paginate ?? 30;
        $configs = ConfigSetting::when($request->has('config_search') && !empty($request->config_search), function ($query) use ($request) {
            $query->where('label', 'like', '%' . $request->config_search . '%');
            $query->orWhere('key', 'like', '%' . $request->config_search . '%');
            $query->orWhere('value', 'like', '%' . $request->config_search . '%');
        })->orderBy('id', 'DESC')->paginate($perPage);
        return view('configuration.index', compact('configs', 'perPage'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('configuration.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        if (!auth()->user()->can('configuration.create')) {
            return abort(403, 'Unauthorized action.');
        }

        $messages = [
            'config_key.required' => 'The key field is required',
            'config_key.unique' => 'The key has already been taken.',
        ];

        // $validateData = $request->validate([
        //     'config_key' => 'required|unique:config_settings,key',
        //     'label' => 'required|string',
        //     'value' => 'required|string',
        //     'environment' => 'required|string',
        // ], $messages);
        $rules = [
            'config_key' => 'required|unique:config_settings,key',
            'label' => 'required|string',
            'value' => 'required|string',
            'environment' => 'required|string',
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors());
        }
        try {
            $configs = ConfigSetting::create([
                'label' => $request->label,
                'key' => $request->config_key,
                'value' => $request->value,
                'environment' => $request->environment,
            ]);
            \Illuminate\Support\Facades\Artisan::call('optimize:clear');
            return redirect()->route('admin.configuration.index')->with([
                'status' => 'Config Created for label ' . $request->label,
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
     * @param  \App\Models\ConfigSetting  $ConfigSetting
     * @return \Illuminate\Http\Response
     */
    public function show(ConfigSetting $ConfigSetting)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ConfigSetting  $ConfigSetting
     * @return \Illuminate\Http\Response
     */
    public function edit(ConfigSetting $configuration)
    {
        return view('configuration.edit', ['configs' => $configuration]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ConfigSetting  $ConfigSetting
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ConfigSetting $configuration)
    {

        if (!auth()->user()->can('configuration.edit')) {
            return abort(403, 'Unauthorized action.');
        }

        $messages = [
            'config_key.required' => 'The key field is required',
            'config_key.unique' => 'The key has already been taken.',
        ];
        // $validateData = $request->validate([
        //     'config_key' => 'required|unique:config_settings,key,' . $configuration->id,
        //     'label' => 'required|string',
        //     'value' => 'required|string',
        //     'environment' => 'required|string',
        // ], $messages);
        $rules = [
            'config_key' => 'required|unique:config_settings,key,' . $configuration->id,
            'label' => 'required|string',
            'value' => 'required|string',
            'environment' => 'required|string',
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors());
        }
        try {
            $configs = $configuration->update([
                'label' => $request->label,
                'key' => $request->config_key,
                'value' => $request->value,
                'environment' => $request->environment,
            ]);
            \Illuminate\Support\Facades\Artisan::call('optimize:clear');
            return redirect()->route('admin.configuration.index')->with([
                'status' => 'Config Updated for label ' . $request->label,
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
     * @param  \App\Models\ConfigSetting  $ConfigSetting
     * @return \Illuminate\Http\Response
     */
    public function destroy(ConfigSetting $configuration)
    {
        if (!auth()->user()->can('configuration.delete')) {
            return abort(403, 'Unauthorized action.');
        }
        try {
            $configuration->delete();
            \Illuminate\Support\Facades\Artisan::call('optimize:clear');
            return redirect()->route('admin.configuration.index')->with([
                'status' => 'Config Deleted Successfully ..!',
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
