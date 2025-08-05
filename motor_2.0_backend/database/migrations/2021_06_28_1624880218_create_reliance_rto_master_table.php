<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRelianceRtoMasterTable extends Migration
{
    public function up()
    {
        Schema::create('reliance_rto_master', function (Blueprint $table) {

		$table->integer('model_region_id_pk');
		$table->text('region_name');
		$table->text('region_code');
		$table->text('state_name');
		$table->text('state_id_fk');
		$table->text('city_or_village_name');
		$table->text('district_name');
		$table->text('model_zone_name');

        });
    }

    public function down()
    {
        Schema::dropIfExists('reliance_rto_master');
    }
}