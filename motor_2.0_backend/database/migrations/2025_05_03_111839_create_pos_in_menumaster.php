<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreatePosInMenumaster extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $existingMenus = DB::table('menu_master')->pluck('menu_slug')->toArray();
        $menuId = DB::table('menu_master')->where('menu_slug', 'operations')->value('menu_id');

        $menu = [
            'menu_id' => '',
            'menu_name' => 'POS',
            'parent_id' => $menuId,
            'menu_slug' => 'pos',
            'menu_url' => '#',
            'menu_icon' => '<i class="nav-icon fas fa-chart-pie"></i>'
        ];
        if (!in_array($menu['menu_slug'], $existingMenus)) {
            DB::table('menu_master')->insert($menu);
        }



        $existingMenus = DB::table('menu_master')->pluck('menu_slug')->toArray();
        $menuId = DB::table('menu_master')->where('menu_slug', 'pos')->value('menu_id');

        $menu = [
            'menu_id' => '',
            'menu_name' => 'Pos service logs',
            'parent_id' => $menuId,
            'menu_slug' => 'pos_service_logs',
            'menu_url' => '/admin/pos-service-logs',
            'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
        ];

        if (!in_array($menu['menu_slug'], $existingMenus)) {
            DB::table('menu_master')->insert($menu);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pos_in_menumaster');
    }
}
