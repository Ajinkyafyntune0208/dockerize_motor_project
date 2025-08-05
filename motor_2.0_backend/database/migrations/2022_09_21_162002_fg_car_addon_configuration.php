<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FgCarAddonConfiguration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fg_car_addon_configuration', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->text('addon_combination')->nullable();
            $table->text('cover_code')->nullable();
            $table->text('age')->nullable();
            $table->text('with_zd')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fg_car_addon_configuration');
    }
}
