<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIffcoPincodeMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('iffco_pincode_master', function (Blueprint $table) {
            $table->integer('pincode');
            $table->string('city_code', 50)->nullable();
            $table->string('city_name', 50)->nullable();
            $table->string('state_code', 50)->nullable();
            $table->string('state_name', 50)->nullable();
            $table->index(['pincode']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('iffco_pincode_master');
    }
}
