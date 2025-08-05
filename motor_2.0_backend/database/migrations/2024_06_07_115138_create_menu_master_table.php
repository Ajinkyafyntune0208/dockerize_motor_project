<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMenuMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('menu_master', function (Blueprint $table) {
            $table->id('menu_id');
            $table->string('menu_name');
            $table->integer('parent_id')->default(0);
            $table->string('menu_slug');
            $table->string('menu_url');
            $table->string('menu_icon');
            $table->enum('status',['Y','N'])->default('Y');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('menu_master');
    }
}
