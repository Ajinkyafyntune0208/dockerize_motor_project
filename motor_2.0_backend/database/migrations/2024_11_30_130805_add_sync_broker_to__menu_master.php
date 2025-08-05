<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Menu;
class AddSyncBrokerToMenuMaster extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $avaliableMenus = Menu::pluck('menu_slug')->toArray();
        $parent_id = Menu::where('menu_slug', 'logs')->value('menu_id'); // 5 

        $menus = [
            [
                'menu_id' => '',
                'menu_name' => 'Sync Brokerage Logs',
                'parent_id' => $parent_id,
                'menu_slug' => 'sync_brokerage_logs',
                'menu_url' => '/admin/BrokerageLogs',
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
        Schema::table('_menu_master', function (Blueprint $table) {
            //
        });
    }
}
