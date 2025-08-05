<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNcbProtectorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('universal_sompo_car_addon_configuration', function (Blueprint $table) {
            $table->string('ncb_protector',10)->default('False')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('universal_sompo_car_addon_configuration', function (Blueprint $table) {
            //
        });
    }
}
