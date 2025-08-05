<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterZoneOfficeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_zone_office', function (Blueprint $table) {
            $table->integer('zone_office_id', true);
            $table->integer('corporate_office_id');
            $table->string('zone_office_name', 20);
            $table->string('zone_office_desc', 200);
            $table->string('status')->default('Active');
            $table->string('created_by', 100);
            $table->dateTime('created_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_zone_office');
    }
}
