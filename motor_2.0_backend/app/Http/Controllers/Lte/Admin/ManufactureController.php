<?php

namespace App\Http\Controllers\Lte\Admin;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class ManufactureController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        // if (!auth()->user()->can('manufacturer.list')) {
        //     return abort(403, 'Unauthorized action.');
        // }
        if (config('app.env') == 'local') {
            $env_folder = 'uat';
        } else if (config('app.env') == 'test') {
            $env_folder = 'production';
        } else if (config('app.env') == 'live') {
            $env_folder = 'production';
        }
        $gcv_manufacturer = $tractor_manufacturer = $pcv_manufacturer = $motor_manufacturer = $bike_manufacturer = $misc_manufacturer = [];
        $type = $request->type;
        switch ($request->type) {
            case 'gcv_manufacturer':
                $file_name = 'gcv_manufacturer.json';
                $typ = 'gcv';
                break;
            case 'pcv_manufacturer':
                $file_name = 'pcv_manufacturer.json';
                $typ = 'pcv';
                break;
            case 'motor_manufacturer':
                $file_name = 'motor_manufacturer.json';
                $typ = 'car';
                break;
            case 'tractor_manufacturer':
                $file_name = 'tractor_manufacturer.json';
                $typ = 'tractor';
                break;
            case 'misc_manufacturer':
                $file_name = 'misc_manufacturer.json';
                $typ = 'misc';
                break;
            case 'bike_manufacturer':
                $file_name = 'bike_manufacturer.json';
                $typ = 'bike';
                break;
            default:
                $file_name = 'gcv_manufacturer.json';
                $typ = 'gcv';
        }
        if ((\Illuminate\Support\Facades\Storage::get('mmv_masters/' . $env_folder . '/' . $file_name))){

            $manufacturer = json_decode(\Illuminate\Support\Facades\Storage::get('mmv_masters/' . $env_folder . '/'. $file_name));
            foreach($manufacturer as $data)
            {
                $data->image_url = file_url('uploads/vehicleModels/'.$typ.'/'. Str::lower(implode('_', explode(' ', $data->manf_name))). '.png');
            }
        }
        return view('admin_lte.manufacturer.index', compact('manufacturer','type'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->can('manufacturer.edit')) {
            return abort(403, 'Unauthorized action.');
        }

        $validator = Validator::make($request->all(), [
           'image' => 'required|mimetypes:image/png|max:1024'
        ]);
        if($validator->fails()) {
            return redirect()->back()->with([
                'status' => $validator->errors()->first(),
                'class' => 'danger',
            ]);
        }

        try {
           $image = $request->file('image'); 
           $data = $image->storeAs($request->path, $request->name);
            if(empty($data))
            {
                return redirect()->route('admin.manufacturer.index')->with([
                    'status' => 'Error occured logo not uploaded..!',
                    'class' => 'danger',
                ]);
            }else{
            return redirect()->route('admin.manufacturer.index')->with([
                'status' => 'Logo Uploaded Successfully..!',
                'class' => 'success',
            ]);
        }
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }


}
