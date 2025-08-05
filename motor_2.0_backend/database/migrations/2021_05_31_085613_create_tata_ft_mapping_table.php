<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTataFtMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tata_ft_mapping', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('make', 50)->default('0');
            $table->string('model', 150)->default('0');
            $table->string('version', 150)->default('0');
            $table->string('model_code', 50)->default('0');
            $table->string('cc', 50)->default('0');
            $table->string('fuel', 50)->default('0');
            $table->string('carrying', 50)->default('0');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tata_ft_mapping');
    }
}
