<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Menu;

class VahanUploadMenuMaster extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $avaliableMenus = Menu::pluck('menu_slug')->toArray();
        $parent_id = Menu::where('menu_slug', 'vahan_service_configurator')->value('menu_id'); 

        $menus = [
            [
                'menu_id' => '',
                'menu_name' => 'vahan upload',
                'parent_id' => $parent_id,
                'menu_slug' => 'vahan_upload',
                'menu_url' => '/admin/vahan-upload',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
        ];

        foreach ($menus as $menu) {
            if (!in_array($menu['menu_slug'], $avaliableMenus)) 
            {   
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
        //
    }
}
