<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateHidePyiAdmintable extends Migration
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
                'menu_name' => 'Allow Previous Insurers On Quote Landing',
                'parent_id' => 37,
                'menu_slug' => 'hide_pyi',
                'menu_url' => '/admin/hide_pyi',
                'menu_icon' => '<i class="nav-icon fas fa-table"></i>'
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
