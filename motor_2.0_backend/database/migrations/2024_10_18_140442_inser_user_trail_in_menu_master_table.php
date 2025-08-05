<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InserUserTrailInMenuMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $parentMenuId = DB::table('menu_master')->where('menu_slug', 'User Management')->value('menu_id');
        $logsMenu = [
            'menu_name' => 'Logs',
            'parent_id' => $parentMenuId,
            'menu_slug' => 'user_trail_logs', // Slug should be consistent
            'menu_url' => '#',
            'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
        ];

        $existingLogsMenu = DB::table('menu_master')->where('menu_slug', 'user_trail_logs')->first();
        if ($existingLogsMenu) {
            DB::table('menu_master')->where('menu_id', $existingLogsMenu->menu_id)->update($logsMenu);
        } else {
            DB::table('menu_master')->insert($logsMenu);
        }

        $logsMenuId = DB::table('menu_master')->where('menu_slug', 'user_trail_logs')->value('menu_id');

        $menus = [
            [
                'menu_name' => 'Trail',
                'parent_id' => $logsMenuId, // Use the correct 'Logs' menu ID
                'menu_slug' => 'user_trail',
                'menu_url' => '/admin/user-trail',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
            [
                'menu_name' => 'Activity Logs',
                'parent_id' => $logsMenuId, // Use the correct 'Logs' menu ID
                'menu_slug' => 'user_activity_logs',
                'menu_url' => '/admin/user-activity-logs',
                'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
            ],
        ];

        foreach ($menus as $menu) {
            $existingMenu = DB::table('menu_master')->where('menu_slug', $menu['menu_slug'])->first();
            if ($existingMenu) {
                DB::table('menu_master')->where('menu_id', $existingMenu->menu_id)->update($menu);
            } else {
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
        });
    }
}
