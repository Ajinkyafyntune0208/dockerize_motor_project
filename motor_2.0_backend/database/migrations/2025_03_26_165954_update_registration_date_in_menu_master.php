<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateRegistrationDateInMenuMaster extends Migration
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
                'menu_name' => 'Update Registration Date',
                'parent_id' => $parent_id,
                'menu_slug' => 'update_registration_date',
                'menu_url' => '/admin/update-registration-date',
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
        Schema::table('menu_master', function (Blueprint $table) {
            //
        });
    }
}
