<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('role.list')) {
            return response('unauthorized action', 401);
        }
        $roles = \Spatie\Permission\Models\Role::all();
        return view('roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!auth()->user()->can('role.create')) {
            return abort(403, 'Unauthorized action.');
        }
        return view('roles.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('role.create')) {
            return response('unauthorized action', 401);
        }
        $validateData =Validator::make($request->all(), [
            'name' => 'required|string',
            'permission' => 'required|array'
        ]);
        if($validateData->fails()){
            return redirect()->back()->with([
                'status' => 'Please select atleast one permission! or Check whether name is correctly entered',
                'class' => 'danger',
            ]);
        }
        try {
            $role = \Spatie\Permission\Models\Role::create(['name' => $request['name']]);
            $role->syncPermissions($request['permission']);
            return redirect()->route('admin.role.index')->with([
                'status' => 'Role Created Successfully ..!',
                'class' => 'success',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something went wrong ' . $e->getMessage() . '...!',
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
        if (!auth()->user()->can('role.edit')) {
            return response('unauthorized action', 401);
        }
        $role = \Spatie\Permission\Models\Role::with('permissions')->where('id', $id)->first();
        $permissions = collect($role->permissions->pluck('name'))->toArray();
        return view('roles.edit', compact('role', 'permissions'));
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
        if (!auth()->user()->can('role.edit')) {
            return response('unauthorized action', 401);
        }
        $validateData =Validator::make($request->all(), [
            'name' => 'required|string',
            'permission' => 'required|array'
        ]);
        if( $validateData->fails() ){

            return redirect()->back()->with([
                'status' => 'Please select atleast one permission! or Check whether name is correctly entered',
                'class' => 'danger',
            ]);
        }
        try {
            $role = \Spatie\Permission\Models\Role::where('id', $id)->first();
            \Spatie\Permission\Models\Role::where('id', $id)->update(['name' => $request['name']]);
            $role->syncPermissions($request['permission']);          
            return redirect()->route('admin.role.index')->with([
                'status' => 'Role Updated Successfully ..!',
                'class' => 'success',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something went wrong ' . $e->getMessage() . '...!',
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
        if (!auth()->user()->can('role.delete')) {
            return response('unauthorized action', 401);
        }
        try {
            $role = \Spatie\Permission\Models\Role::find($id);
            $role->syncPermissions([]);
            $role->delete();
        } catch (\Exception $e) {
            return redirect()->route('admin.role.index')->with([
                'status' => 'Something went wrong. ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
        return redirect()->route('admin.role.index')->with([
            'status' => 'Role Deleted Successfully..!',
            'class' => 'success',
        ]);
    }
}
