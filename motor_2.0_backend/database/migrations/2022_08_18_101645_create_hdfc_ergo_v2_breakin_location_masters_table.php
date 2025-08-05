<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHdfcErgoV2BreakinLocationMastersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hdfc_ergo_v2_breakin_location_master', function (Blueprint $table) {
            $table->integer('breakin_loc_master_id')->nullable();
            $table->string('breakin_loc_master_name')->nullable();
            $table->string('city_id')->nullable();
            $table->string('is_active')->nullable();
            $table->integer('breakin_loc_master_cd')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hdfc_ergo_v2_breakin_location_master');
    }
}
