<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUnitedIdvPercentagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('united_idv_percentages', function (Blueprint $table) {
            $table->id();
            $table->string('age_interval', 10);
            $table->string('percentage', 10);
        });
        \Illuminate\Support\Facades\Artisan::call('db:seed --class=unitedIdvPercentage');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('united_idv_percentages');
    }
}
