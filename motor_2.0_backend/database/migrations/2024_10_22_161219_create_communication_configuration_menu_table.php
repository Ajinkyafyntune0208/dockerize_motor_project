<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateCommunicationConfigurationMenuTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $existingMenus = DB::table('menu_master')->pluck('menu_slug')->toArray();
        $menuId = DB::table('menu_master')->where('menu_slug', 'configurations')->value('menu_id');

        $menus = [
            [
                'menu_id' => '',
                'menu_name' => 'Communication Configuration',
                'parent_id' => $menuId,
                'menu_slug' => 'communication_configuration',
                'menu_url' => '/admin/communication-configuration',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>',
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
