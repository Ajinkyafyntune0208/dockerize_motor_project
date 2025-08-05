<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('category', function (Blueprint $table) {
            $table->integer('category_id', true);
            $table->string('category_name', 50);
            $table->string('category_desc', 50);
            $table->string('status')->default('Active');
            $table->string('created_by', 50);
            $table->dateTime('created_date')->useCurrent();
            $table->string('updated_by', 50);
            $table->dateTime('updated_date');
            $table->string('deleted_by', 50);
            $table->dateTime('deleted_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('category');
    }
}
