<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExcDataValidationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('exc_data_validation', function (Blueprint $table) {
            $table->integer('exc_id', true);
            $table->string('error_code', 50)->nullable();
            $table->string('type_of_exc', 50)->nullable();
            $table->string('error_desc', 200)->nullable();
            $table->string('error_in_module', 200)->nullable();
            $table->string('field', 200)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exc_data_validation');
    }
}
