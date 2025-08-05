<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTataAigPincodeMasterTable extends Migration
{
    public function up()
    {
        Schema::create('tata_aig_pincode_master', function (Blueprint $table) {

		$table->text('num_state_cd')->nullable();
		$table->text('txt_state')->nullable();
		$table->text('num_citydistrict_cd')->nullable();
		$table->text('num_pincode')->nullable();
		$table->text('txt_pincode_locality')->nullable();
		$table->text('dat_start_dt')->nullable();
		$table->text('dat_end_dt')->nullable();
		$table->text('num_insert_trans_id')->nullable();
		$table->text('num_modify_trans_id')->nullable();
		$table->text('dat_insert_dt')->nullable();
		$table->text('dat_modify_dt')->nullable();
		$table->text('num_city_cd')->nullable();
		$table->text('txt_user_id')->nullable();
		$table->text('txt_ip_address')->nullable();
		$table->text('num_country_cd')->nullable();
		$table->text('txt_is_rural')->nullable();
		$table->text('num_tehsil_cd')->nullable();

        });
    }

    public function down()
    {
        Schema::dropIfExists('tata_aig_pincode_master');
    }
}