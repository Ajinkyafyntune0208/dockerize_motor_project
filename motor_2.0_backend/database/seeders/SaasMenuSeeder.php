<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SaasMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $existingMenus = DB::table('menu_master')->pluck('menu_slug')->toArray();
        $menus = [
            [
                'menu_name' => 'Communication Configuration',
                'parent_id' => 38,
                'menu_slug' => 'communication-configuration',
                'menu_url' => '/admin/communication-configuration',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
        ];
        foreach ($menus as $menu) {
            if (!in_array($menu['menu_slug'], $existingMenus)) {
                DB::table('menu_master')->insert($menu);
            }
    
        }
    }
}
