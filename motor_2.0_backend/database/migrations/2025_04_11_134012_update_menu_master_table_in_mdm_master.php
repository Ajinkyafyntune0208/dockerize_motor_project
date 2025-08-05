<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateMenuMasterTableInMdmMaster extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $existingMenus = Menu::pluck('menu_slug')->toArray();
        $menuId =Menu::where('menu_slug', 'masters')->value('menu_id');

        $menus = [
            [
                'menu_id' => '',
                'menu_name' => 'Master Mdm Sync',
                'parent_id' => $menuId,
                'menu_slug' => 'master_mdm_sync',
                'menu_url' => '/admin/mdm_master',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
        ];

       foreach ($menus as $menu) {
        if (!in_array($menu['menu_slug'], $existingMenus)) {
            Menu::insert($menu);
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
        Schema::table('menu_master', function (Blueprint $table) {
            //
        });
    }
}
