<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateMmvBlockerAdminTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $existingMenus = DB::table('menu_master')->pluck('menu_slug')->toArray();
        $menuId = DB::table('menu_master')->where('menu_slug', 'manage')->value('menu_id');

        $menus = [
            [
                'menu_id' => '',
                'menu_name' => 'Manufacturer Selector',
                'parent_id' => $menuId,
                'menu_slug' => 'make_selector',
                'menu_url' => '/admin/make_selector',
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
        Schema::table('menu_master', function (Blueprint $table) {
        });
    }
}