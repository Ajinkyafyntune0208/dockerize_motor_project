<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMenuTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('menu', function (Blueprint $table) {
            $table->integer('menu_id', true);
            $table->string('menu_name');
            $table->string('menu_desc');
            $table->dateTime('created_on');
            $table->dateTime('updated_date');
            $table->string('updated_by', 50);
            $table->string('deleted_by', 50);
            $table->dateTime('deleted_date');
            $table->integer('parent_id')->default(0)->comment('0 if menu is root level or menuid if this is child on any menu');
            $table->string('link');
            $table->string('status')->default('Active')->comment('Deactive for disabled menu or Active for enabled menu');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('menu');
    }
}
