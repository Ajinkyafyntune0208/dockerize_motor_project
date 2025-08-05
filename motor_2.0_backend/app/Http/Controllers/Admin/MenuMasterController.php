<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Menu;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use DB;

class MenuMasterController extends Controller
{

    public function index(Request $request){
        if (!auth()->user()->can('menu.list')) {
            abort(403, 'Unauthorized action.');
        }
        $data['menus'] = Menu::select('menu_id','menu_name','parent_id','menu_slug','status')->whereNull('deleted_at')->orderBy('menu_id','desc')->get();
        return view('admin_lte.menu.index',$data);
    }

    public function create(Request $request){
        $data['menus'] = Menu::select('menu_name','menu_id')->whereNull('deleted_at')->where('status','Y')->orderBy('menu_id','desc')->get();
        return view('admin_lte.menu.create',$data);
    }

    public function store(Request $request){
        $rules = [
            'parent_id'=>'required|integer',
            'menu_name'=>'required|string|max:255',
            'menu_slug'=>'required|string|max:255',
            'menu_url' =>'required|string',
            'menu_icon'=>'required|string',
            'status'   =>'required|string|max:255'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }
        else{
            $inserted = Menu::create($request->all());
            if ($inserted){
                return redirect()->route('admin.menu.index')->with(['message' => 'Manu Created Successfully.','class' => 'success']);
            } else {
                return redirect()->back()->withErrors(['message' => 'Error While Creating Manu.'])->withInput();
            }
        }
    }

    public function edit(Request $request,$menu_id){
        $data['menus'] = Menu::select('menu_name','menu_id')->whereNull('deleted_at')->where('status','Y')->orderBy('menu_id','desc')->get();
        $data['menu_details'] = Menu::where('menu_id', base64_decode($menu_id))->first();
        return view('admin_lte.menu.edit',$data);
    }

    public function update(Request $request)
    {
        $rules = [
            'menu_id'  =>'required|integer',
            'parent_id'=>'required|integer',
            'menu_name'=>'required|string|max:255',
            'menu_slug'=>'required|string|max:255',
            'menu_url' =>'required|string',
            'menu_icon'=>'required|string',
            'status'   =>'required|string|max:255'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }
        else{
            $updated = Menu::where('menu_id', $request->menu_id)->update([
                'parent_id' => $request->parent_id,
                'menu_name' => $request->menu_name,
                'menu_slug' => $request->menu_slug,
                'menu_url' => $request->menu_url,
                'menu_icon' => $request->menu_icon,
                'status'    => $request->status,
            ]);

            if ($updated){
                return redirect()->route('admin.menu.index')->with(['message' => 'Manu Updated Successfully.','class' => 'success']);
            } else {
                return redirect()->back()->withErrors(['message' => 'Error While Updating Manu.'])->withInput();
            }
        }

    }

    public function destroy($id){
        $menu = Menu::find($id);
        if ($menu->delete()) {
            return redirect()->route('admin.menu.index')->with([
                'message' => 'Menu Deleted Successfully.',
                'class' => 'success',
            ]);
        } else {
            return redirect()->route('admin.menu.index')->with([
                'message' => 'Error While Deleting the Menu.',
                'class' => 'danger',
            ]);
        }
    }

    public function getMenu()
    {
        $permissions = DB::table('permissions')
            ->whereNotIn('name', DB::table('role_has_permissions')
                ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
                ->where('role_id', auth()->user()->roles->first()->id)
                ->pluck('permissions.name')
                ->toArray())
            ->pluck('name')
            ->toArray();

        $duplicatePermissions = DB::table('permissions')
        ->join('role_has_permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
        ->where('role_id',auth()->user()->roles->first()->id)
        ->pluck('permissions.name')
        ->toArray();

        $duplicatePermissions = array_unique(array_map(function ($value) {
            return explode('.', $value)[0];
        }, $duplicatePermissions));

        $permissions = array_unique(array_map(function ($value) {
            return explode('.', $value)[0];
        }, $permissions));

        $permissions = array_values(array_diff($permissions, $duplicatePermissions));

        $all = Menu::where('status', 'Y')
            ->whereNotIn('menu_slug', $permissions)
            ->orderby('menu_name')
            ->get()
            ->toArray();

        $menu = array_filter($all, function ($value) {
            return $value['parent_id'] == 0;
        });

        $currentUrl = '/' . request()->path(); // Get the current URL

        $menuHtml = $this->menuNested($menu, $all, $currentUrl);

        return $menuHtml;
    }

    public function menuNested($menus, $all, $currentUrl = null)
    {
        $html = '';
        foreach ($menus as $key => $menu) {
            $child = array_filter($all, function ($value) use ($menu) {
                return $value['parent_id'] == $menu['menu_id'];
            });

            $isActive = $currentUrl && (strpos($currentUrl, $menu['menu_url']) !== false);
            $hasActiveChild = $this->hasActiveChild($child, $all, $currentUrl);

            if ($menu['menu_url'] == '#' && !$child) {
                continue;
            }
            if($isActive == true || $hasActiveChild == true){

                $html .= "<li class='nav-item menu-is-opening menu-open'>";
            } else {
                $html .= "<li class='nav-item'>";
            }

            if($isActive == true || $hasActiveChild == true){
                $html .= "<a href='" . env('APP_URL') . "{$menu['menu_url']}' class='nav-link active'>";
            } else {
                $html .= "<a href='" . env('APP_URL') . "{$menu['menu_url']}' class='nav-link'>";
            }
            $html .= "{$menu['menu_icon']}<p>{$menu['menu_name']}";

            if ($child) {
                $html .= "<i class='right fas fa-angle-left'></i>";
            }

            $html .= "</p></a>";

            if ($child) {
                $html .= "<ul class='nav nav-treeview' style='padding-left: 1rem'>";
                $html .= $this->menuNested($child, $all, $currentUrl);
                $html .= "</ul>";
            }

            $html .= "</li>";
        }

        return $html;
    }


    private function hasActiveChild($menus, $all, $currentUrl)
{
    foreach ($menus as $menu) {
        if (strpos($currentUrl, $menu['menu_url']) !== false) {
            return true;
        }

        $child = array_filter($all, function ($value) use ($menu) {
            return $value['parent_id'] == $menu['menu_id'];
        });

        if ($this->hasActiveChild($child, $all, $currentUrl)) {
            return true;
        }
    }
    return false;
}
}
