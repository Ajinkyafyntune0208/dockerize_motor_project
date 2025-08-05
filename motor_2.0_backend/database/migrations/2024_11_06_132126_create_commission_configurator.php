<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommissionConfigurator extends Migration
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
                'menu_name' => 'Commision Configurator',
                'parent_id' => $menuId,
                'menu_slug' => 'commision_configurator',
                'menu_url' => '/admin/ic-config/commision_configurator',
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
        Schema::dropIfExists('commission_configurator');
    }
}
