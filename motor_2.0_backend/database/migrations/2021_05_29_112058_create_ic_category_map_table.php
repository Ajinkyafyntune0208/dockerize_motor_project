<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIcCategoryMapTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ic_category_map', function (Blueprint $table) {
            $table->integer('ic_category_map_id', true);
            $table->integer('ic_id')->nullable();
            $table->integer('category_id')->nullable();
            $table->string('status')->nullable()->default('Active');
            $table->integer('updated_by')->nullable();
            $table->dateTime('updated_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ic_category_map');
    }
}
