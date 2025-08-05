<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSurveyorCategoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('surveyor_category', function (Blueprint $table) {
            $table->integer('surveyor_category_id', true);
            $table->string('surveyor_category_name', 20);
            $table->dateTime('created_date')->useCurrent();
            $table->dateTime('updated_date')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('surveyor_category');
    }
}
