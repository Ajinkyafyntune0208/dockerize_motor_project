<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AbandonDataInMenuMaster extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $menus = [];
        $existingMenus = DB::table('menu_master')->pluck('menu_slug')->toArray();
        $menuId = DB::table('menu_master')->where('menu_slug', 'ic_configuration')->value('menu_id');

        $menus = [
            [
                'menu_id' => '',
                'menu_name' => 'PA insurance masters',
                'parent_id' => $menuId,
                'menu_slug' => 'pa_insurance_masters',
                'menu_url' => '/admin/pa-insurance-masters',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => '',
                'menu_name' => 'Kotak CKYC Response Decrypt',
                'parent_id' => $menuId,
                'menu_slug' => 'kotak_encrypt_decrypt',
                'menu_url' => '/admin/kotak-encrypt-decrypt',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => '',
                'menu_name' => 'IC Return URL',
                'parent_id' => $menuId,
                'menu_slug' => 'ic_return_url',
                'menu_url' => '/admin/ic-return-url',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ]
        ];
        foreach ($menus as $menu) {
            if (!in_array($menu['menu_slug'], $existingMenus)) {
                DB::table('menu_master')->insert($menu);
            }
        }
        $menuId = DB::table('menu_master')->where('menu_slug', 'misc')->value('menu_id');

        $menus = [
            [
                'menu_id' => '',
                'menu_name' => 'MMV RTO Data Sync',
                'parent_id' => $menuId,
                'menu_slug' => 'mmv_rto_data_sync',
                'menu_url' => '/api/getRtoData',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_id' => '',
                'menu_name' => 'MMV Sync',
                'parent_id' => $menuId,
                'menu_slug' => 'mmv_sync',
                'menu_url' => '/api/car/getdata',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ]
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
            //
        });
    }
}
