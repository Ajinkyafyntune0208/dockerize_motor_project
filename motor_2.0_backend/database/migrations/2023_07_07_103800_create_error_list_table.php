<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateErrorListTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('error_list', function (Blueprint $table) {
            $table->increments('id')->index();
            $table->string('error_name')->index();
            $table->string('error_code')->index();
            $table->string('ic_name')->index();
            $table->enum('status', array('Y', 'N'))->default('N');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('error_list');
    }
}
