<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterOccupationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_occupation', function (Blueprint $table) {
            $table->integer('occupation_id', true);
            $table->string('occupation_code')->nullable();
            $table->string('occupation_name')->nullable();
            $table->string('company_alias')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_occupation');
    }
}
