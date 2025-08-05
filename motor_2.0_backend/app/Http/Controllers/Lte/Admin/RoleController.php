<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Exports\UserTrailsExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserTrail;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

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
        return view('admin_lte.role.index', compact('roles'));
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
        $menu = Menu::where('menu_url','!=', '#')->orderByRaw("CASE WHEN menu_name = 'Dashboard' THEN 0
         ELSE 1 END,menu_name")->get();
        return view('admin_lte.role.create', compact('menu'));
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
            $permissionsToGrant = [];
            $selectedPermissions = $request['permission'];
            $uniqueParentSlugs = array_unique(array_map(function($permission) {
                return explode('.', $permission)[0];
            }, $selectedPermissions));

            foreach ($uniqueParentSlugs as $parentSlug) {
                $menu = DB::table('menu_master')->where('menu_slug', $parentSlug)->first();

                if ($menu) {
                    $this->addMenuPermissions($menu, $permissionsToGrant, $selectedPermissions);
                }
            }
            foreach ($permissionsToGrant as $permissionSlug) {
                Permission::firstOrCreate([
                    'name' => $permissionSlug
                ], [
                    'guard_name' => 'web'
                ]);
            }
            $role = \Spatie\Permission\Models\Role::create(['name' => $request['name']]);
            $role->syncPermissions($permissionsToGrant);
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
        $additional_permissions = DB::table('permissions as p')
        ->select(
            'p.id as permission_id',
            'p.name as permission_name',
            DB::raw('IF(rp.permission_id IS NULL, 0, 1) AS status')
        )
        ->leftJoin(DB::raw('(
        SELECT CONCAT_WS(".", mm1.menu_slug, "list") AS permission FROM menu_master AS mm1
        UNION ALL
        SELECT CONCAT_WS(".", mm2.menu_slug, "show") AS permission FROM menu_master AS mm2
        UNION ALL
        SELECT CONCAT_WS(".", mm3.menu_slug, "create") AS permission FROM menu_master AS mm3
        UNION ALL
        SELECT CONCAT_WS(".", mm4.menu_slug, "edit") AS permission FROM menu_master AS mm4
        UNION ALL
        SELECT CONCAT_WS(".", mm5.menu_slug, "delete") AS permission FROM menu_master AS mm5
    ) as mm'), 'p.name', '=', 'mm.permission')
        ->leftJoin('role_has_permissions as rp', function ($join) use ($id) {
            $join->on('p.id', '=', 'rp.permission_id')
            ->where('rp.role_id', '=', $id);  // Use the role ID in the query
        })
            ->whereNull('mm.permission')
            ->having('status', '=', 1)  // Filter to only return records with status = 1
            ->orderBy('p.name')
            ->get();
        $role = \Spatie\Permission\Models\Role::with('permissions')->where('id', $id)->first();
        $permissions = collect($role->permissions->pluck('name'))->toArray();
        $menu = Menu::where('menu_url','!=', '#')->orderby('menu_id','asc')->get();
        $quicklink = DB::table('role_has_quick_link')->where('role_id', $id)->first();
        return view('admin_lte.role.edit', compact('role', 'permissions', 'menu' , 'additional_permissions' , 'quicklink'));
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

        if ($validateData->fails()) {
            return redirect()->back()->with([
                'status' => 'Please select atleast one permission or check whether the name is correctly entered.',
                'class' => 'danger',
            ]);
        }
        try {
            $permissionsToGrant = [];
            $selectedPermissions = $request['permission'];
            $uniqueParentSlugs = array_unique(array_map(function($permission) {
                return explode('.', $permission)[0];
            }, $selectedPermissions));

            foreach ($uniqueParentSlugs as $parentSlug) {
                $menu = DB::table('menu_master')->where('menu_slug', $parentSlug)->first();

                if ($menu) {
                    $this->addMenuPermissions($menu, $permissionsToGrant, $selectedPermissions);
                }
            }
            if(!empty($request['additionalPermission'])){
                $permissionsToGrant =  array_merge($permissionsToGrant , $request['additionalPermission']);
            }
            foreach ($permissionsToGrant as $permissionSlug) {
                Permission::firstOrCreate([
                    'name' => $permissionSlug
                ], [
                    'guard_name' => 'web'
                ]);
            }
            $role = Role::findOrFail($id);
            $existingPermissions = $role->permissions->pluck('name')->toArray();
            $role->syncPermissions($permissionsToGrant);
            $role->givePermissionTo($permissionsToGrant);
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
     * Recursively add menu permissions and its ancestors.
     *
     * @param object $menu
     * @param array &$permissions
     * @return void
     */
    private function addMenuPermissions($menu, &$permissions, $selectedPermissions , $isParent = false)
    {
        $isChildMenuSelected = in_array($menu->menu_slug . '.list', $selectedPermissions)
            || in_array($menu->menu_slug . '.show', $selectedPermissions)
            || in_array($menu->menu_slug . '.create', $selectedPermissions)
            || in_array($menu->menu_slug . '.edit', $selectedPermissions)
            || in_array($menu->menu_slug . '.delete', $selectedPermissions);

        if ($isChildMenuSelected) {
            foreach ($selectedPermissions as $permission) {
                if (strpos($permission, $menu->menu_slug) === 0 && !in_array($permission, $permissions)) {
                    $permissions[] = $permission;
                }
            }
        } else {

        $extensions = ['.list', '.show', '.create', '.edit', '.delete'];
        if($isParent){
            $extensions = ['.list','.show'];
        }
        foreach ($extensions as $extension) {
            $permission = $menu->menu_slug . $extension;
            if (!in_array($permission, $permissions)) {
                $permissions[] = $permission;
                }
            }
        }

        if ($menu->parent_id) {
            $parentMenu = DB::table('menu_master')->where('menu_id', $menu->parent_id)->first();
            if ($parentMenu) {
                $this->addMenuPermissions($parentMenu, $permissions, $selectedPermissions , true);
            }
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

    public function getPermission(Request $request)
    {
        $id = $request->id;
        $permissions = DB::table('permissions as p')
            ->select(
                'p.id as permission_id',
                'p.name as permission_name',
                DB::raw('IF(rp.permission_id IS NULL, 0, 1) AS status')
            )
            ->leftJoin(DB::raw('(
                SELECT CONCAT_WS(".", mm1.menu_slug, "list") AS permission FROM menu_master AS mm1
                UNION ALL
                SELECT CONCAT_WS(".", mm2.menu_slug, "show") AS permission FROM menu_master AS mm2
                UNION ALL
                SELECT CONCAT_WS(".", mm3.menu_slug, "create") AS permission FROM menu_master AS mm3
                UNION ALL
                SELECT CONCAT_WS(".", mm4.menu_slug, "edit") AS permission FROM menu_master AS mm4
                UNION ALL
                SELECT CONCAT_WS(".", mm5.menu_slug, "delete") AS permission FROM menu_master AS mm5
            ) as mm'), 'p.name', '=', 'mm.permission')
            ->leftJoin('role_has_permissions as rp', function ($join) use ($id) {
                $join->on('p.id', '=', 'rp.permission_id')
                    ->where('rp.role_id', '=', $id);  // Use the role ID in the query
            })
            ->whereNull('mm.permission')
            ->orderBy('p.name')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $permissions
        ]);
    }

    public function savePermission(Request $request)
    {

        if (!auth()->user()->can('role.edit')) {
            return response('unauthorized action', 401);
        }
        $validateData = Validator::make($request->all(), [
            'name' => 'required|string',
            'permission' => 'nullable|array',
            'additional_info' => 'nullable|string'
        ]);

        if ($validateData->fails()) {
            return redirect()->back()->with([
                'status' => 'Please select atleast one permission or check whether the name is correctly entered.',
                'class' => 'danger',
            ]);
        }
        try {
            $additional_info = trim($request->input('additional_info'));
            if (!empty($additional_info)) {
                $additional_text_permission[] = $additional_info;
            } else {
                $additional_text_permission = [];
            }
            $additional_permission = !empty($request['permission']) ? $request['permission'] :   [];
            $id = $request['role_id'];
            $role = Role::findOrFail($id);
            $existingPermissions = $role->permissions->pluck('name')->toArray();
            $additional_permissions = DB::table('permissions as p')
            ->select(
                'p.name',
            )
            ->leftJoin(DB::raw('(
                SELECT CONCAT_WS(".", mm1.menu_slug, "list") AS permission FROM menu_master AS mm1
                UNION ALL
                SELECT CONCAT_WS(".", mm2.menu_slug, "show") AS permission FROM menu_master AS mm2
                UNION ALL
                SELECT CONCAT_WS(".", mm3.menu_slug, "create") AS permission FROM menu_master AS mm3
                UNION ALL
                SELECT CONCAT_WS(".", mm4.menu_slug, "edit") AS permission FROM menu_master AS mm4
                UNION ALL
                SELECT CONCAT_WS(".", mm5.menu_slug, "delete") AS permission FROM menu_master AS mm5
            ) as mm'), 'p.name', '=', 'mm.permission')
            ->leftJoin('role_has_permissions as rp', function ($join) use ($id) {
                $join->on('p.id', '=', 'rp.permission_id')
                ->where('rp.role_id', '=', $id);
            })
                ->whereNull('mm.permission')->pluck('p.name')
                ->toArray();
            $existingPermissions = array_diff($existingPermissions , $additional_permissions);
            $permissionsToGrant = [];
            $selectedPermissions = $existingPermissions;
            $uniqueParentSlugs = array_unique(array_map(function ($permission) {
                return explode('.', $permission)[0];
            }, $existingPermissions));

            foreach ($uniqueParentSlugs as $parentSlug) {
                $menu = DB::table('menu_master')->where('menu_slug', $parentSlug)->first();

                if ($menu) {
                    $this->addMenuPermissions($menu, $permissionsToGrant, $selectedPermissions);
                }
            }
            $permissionsToGrant = array_merge($permissionsToGrant, $additional_permission ,$additional_text_permission );
            foreach ($permissionsToGrant as $permissionSlug) {
                Permission::firstOrCreate([
                    'name' => $permissionSlug
                ], [
                    'guard_name' => 'web'
                ]);
            }

            $role->syncPermissions($permissionsToGrant);
            $role->givePermissionTo($permissionsToGrant);
            return response()->json([
                'status' => true,
                'message' => !empty($request['permission']) ? "Permission saved Succcessfullyy" : ($request->input('additional_info') ? "Custom Permission Saved Successfully" :"No Permission Selected")
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
            ]);
        }
    }

    public function getQuickLink(Request $request)
    {
        $roleId = $request->id;
        $results = DB::table('role_has_permissions as rp')
        ->distinct()
        ->select(
            'mm.menu_name',
            'mm.menu_id',
            DB::raw('GROUP_CONCAT(rp.permission_id) as permission_id'),
            DB::raw('IF(rq.permission_id IS NOT NULL, true, false) as status')
        )
            ->leftJoin('permissions as p', function ($join) use ($roleId) {
                $join->on('rp.permission_id', '=', 'p.id')
                ->where(function ($query) {
                    $query->where('p.name', 'like', '%.list')
                        ->orWhere('p.name', 'like', '%.show');
                })
                    ->where('rp.role_id', $roleId);
            })
            ->join('menu_master as mm', DB::raw('mm.menu_slug'), '=', DB::raw('SUBSTRING_INDEX(p.name, \'.\', 1)'))
            ->leftJoin('role_has_quick_link as rq', function ($join) use ($roleId) {
                $join->on('rp.permission_id', '=', 'rq.permission_id')
                ->where('rq.role_id', '=', $roleId);
            })
            ->where('mm.menu_url', '<>', '#')
            ->groupBy('mm.menu_name', 'mm.menu_id')
            ->orderBy('mm.menu_name')
            ->get()
            ->toArray();
        return response()->json([
            'status' => true,
            'data' => $results
        ]);
    }

    public function saveQuickLink(Request $request)
    {

        $roleId = $request->input('role_id');
        $authorizationStatus = $request->input('authorization_status', 'off');
        $permissions = $request->input('permission', []);
        DB::table('role_has_quick_link')->where('role_id', $roleId)->delete();
        if(empty($request->input('authorization_status',))){
            return response()->json([
                'status' => true,
                'message' => "Custom Quick Link : InActivated"
            ]);
        }
        if(empty($request->input('permission'))){
            return response()->json([
                'status' => true,
                'message' => "No Link Selected | Default : InActived"
            ]);
        }
        foreach ($permissions as $permission) {
            list($menu_id,$permission_id) = explode(',', $permission);
            DB::table('role_has_quick_link')->insert([
                'role_id' => $roleId,
                'menu_id' => $menu_id,
                'permission_id' => $permission_id,
                'authorization_status' => $authorizationStatus,
                'created_by' => auth()->user()->id,
                'updated_by' => auth()->user()->id,
            ]);
        }
        return response()->json([
            'status' => true,
            'message' => "Link Store Successfully"
        ]);
    }

    public function userTrail()
    {
        $users = User::all();
        return view('admin_lte.role.user-trail', compact('users'));
    }

    public function filterUserTrails(Request $request)
    {
        $validateData = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validateData->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validateData->errors()->first(),
            ], 400);
        }

        $query = UserTrail::query();

        if ($request->has('user_ids') && !empty($request->user_ids)) {
            $query->whereIn('user_id', $request->user_ids);
        }

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $data = $query->get();
        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }

    public function exportUserTrails(Request $request)
    {
        $user_id = $request->get('user_id');
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');

        return Excel::download(new UserTrailsExport($user_id, $start_date, $end_date), 'user_trails.xls');
    }
}
