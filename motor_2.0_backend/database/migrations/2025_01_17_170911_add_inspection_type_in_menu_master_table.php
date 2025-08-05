<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddInspectionTypeInMenuMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $existingMenus = DB::table('menu_master')->pluck('menu_slug')->toArray();
        $menuId =DB::table('menu_master')->where('menu_slug', 'configuration')->value('menu_id');

        $menus = [
            [
                'menu_id' => '',
                'menu_name' => 'Inspection Type',
                'parent_id' => $menuId,
                'menu_slug' => 'inspection_type',
                'menu_url' => '/admin/inspection',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
        ];

       foreach ($menus as $menu) {
        if (!in_array($menu['menu_slug'], $existingMenus)) {
            DB::table('menu_master')->insert($menu);
        }
    }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        
    }
}
