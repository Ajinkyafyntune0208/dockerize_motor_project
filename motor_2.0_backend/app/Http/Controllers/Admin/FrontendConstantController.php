<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\FrontendConstant;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class FrontendConstantController extends Controller
{
    public function index(Request $request)
    {
        $data = FrontendConstant::orderBy('id','DESC')->get();
        return view('frontend-constant.index',compact('data'));
    }

    
    public function create(){
        //
    }


    public function store(Request $request)
    {
        $frontend_constant = FrontendConstant::Create([
            'key'     => $request->new_key,
            'value'     =>  $request->new_value,
            'datatype'     => $request->new_datatype,
        ]);

        return response()->json([
            "status" => $frontend_constant ? true : false,
        ]);
    }

    
    public function show($id)
    {
        //
    }

    
    public function check(Request $request)
    {
        $matchThese = ['value' => $request->value, 'key' => $request->key_value];
            return response()->json([
                "status" => FrontendConstant::where($matchThese)->exists() ? true : false,
            ]);
    }

    public function update(Request $request)
    {
        $frontend_const = FrontendConstant::updateOrCreate([
            'id'   => $request->id,
        ], [
            'datatype'     => $request->label,
            'key'     => $request->key,
            'value'     => $request->val
        ]);
        return response()->json([
            "status" => $frontend_const ? true : false,
        ]);
    }

    
    public function destroy(Request $request)
    {
        $deleted = FrontendConstant::find($request->id)->delete();
        return response()->json([
            "status" => $deleted ? true : false,
        ]);
    }
}