<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddConfigBootInMenuMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $existingMenus = DB::table('menu_master')->pluck('menu_slug')->toArray();
        $menuId = DB::table('menu_master')->where('menu_slug', 'configuration')->value('menu_id');

        $menus = [
            [
                'menu_name'   => 'Config Boot',
                'parent_id'   => $menuId,
                'menu_slug'   => 'config_boot',
                'menu_url'    => '/admin/boot-config',
                'menu_icon'   => '<i class="far fa-circle nav-icon"></i>'
            ],
        ];

        if (!in_array('config_boot', $existingMenus)) {
            DB::table('menu_master')->insert($menus);
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
