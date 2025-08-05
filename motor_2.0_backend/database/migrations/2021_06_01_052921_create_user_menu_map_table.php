<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserMenuMapTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_menu_map', function (Blueprint $table) {
            $table->integer('user_menu_map_id', true);
            $table->integer('user_id')->nullable();
            $table->integer('menu_id')->nullable();
            $table->string('menu_add')->default('Active');
            $table->string('menu_edit')->default('Active');
            $table->string('menu_view')->default('Active');
            $table->string('menu_delete')->default('Active');
            $table->string('status')->nullable()->default('Active');
            $table->dateTime('created_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_menu_map');
    }
}
