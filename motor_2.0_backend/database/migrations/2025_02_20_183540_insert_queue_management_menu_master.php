<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Menu;

class InsertQueueManagementMenuMaster extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $avaliableMenus = Menu::pluck('menu_slug')->toArray();
        $parent_id = Menu::where('menu_slug', 'misc')->value('menu_id'); 

        $menus = [
            [
                'menu_id' => '',
                'menu_name' => 'Queue Management',
                'parent_id' => $parent_id,
                'menu_slug' => 'queue_management',
                'menu_url' => '/admin/queue-management',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
        ];

        foreach ($menus as $menu) {
            if (!in_array($menu['menu_slug'], $avaliableMenus)) // Checking if the slug already exists
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
