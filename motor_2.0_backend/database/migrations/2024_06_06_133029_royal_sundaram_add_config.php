<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;


class RoyalSundaramAddConfig extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Artisan::call('db:seed --class=HdfcCarV1ConfigSeeder');

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
