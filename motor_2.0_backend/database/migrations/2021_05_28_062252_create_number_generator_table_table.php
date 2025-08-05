<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNumberGeneratorTableTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('number_generator_table', function (Blueprint $table) {
            $table->integer('number_gen_id', true);
            $table->date('date');
            $table->integer('serial_no');
            $table->string('for_purpose', 50);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('number_generator_table');
    }
}
