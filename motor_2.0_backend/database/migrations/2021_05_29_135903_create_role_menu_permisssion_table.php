<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoleMenuPermisssionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('role_menu_permisssion', function (Blueprint $table) {
            $table->integer('map_id', true);
            $table->integer('role_id')->nullable();
            $table->integer('menu_id')->nullable();
            $table->string('menu_add')->nullable()->default('Inactive');
            $table->string('menu_edit')->nullable()->default('Inactive');
            $table->string('menu_view')->nullable()->default('Inactive');
            $table->string('menu_delete')->nullable()->default('Inactive');
            $table->string('status')->nullable()->default('Active');
            $table->dateTime('created_date')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('role_menu_permisssion');
    }
}
