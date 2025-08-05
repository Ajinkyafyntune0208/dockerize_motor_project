<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


class UpdateIciciMasterDownloadMenu extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        $menu = DB::table('menu_master')->where('menu_name', 'ICICI Master Download')->first();

        if ($menu) {
            DB::table('menu_master')->where('menu_name', 'ICICI Master Download')
            ->update([
                    'menu_name' => 'ICICI Master Download (defunct)',
                    'status' => 'N'
                ]);
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