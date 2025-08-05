<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddMenuForCommission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('menu_master')->insert([
            'menu_name' => 'Commission Api Logs',
            'parent_id' => 5,
            'menu_slug' => 'commission_api_logs',
            'menu_url' => '/admin/commission-api-logs',
            'menu_icon' => '<i class="far fa-circle nav-icon"></i>'
        ]);
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
