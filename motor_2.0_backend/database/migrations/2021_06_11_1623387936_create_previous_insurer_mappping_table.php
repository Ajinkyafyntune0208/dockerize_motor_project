<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePreviousInsurerMapppingTable extends Migration
{
    public function up()
    {
        Schema::create('previous_insurer_mappping', function (Blueprint $table) {

		$table->string('previous_insurer',100)->nullable()->default('NULL');
		$table->string('company_alias',100)->nullable()->default('NULL');
		$table->string('oriental',100)->nullable()->default('NULL');
		$table->string('acko',100)->nullable()->default('NULL');
		$table->string('sbi',100)->nullable()->default('NULL');
		$table->string('bajaj_allianz',50)->nullable()->default('NULL');
		$table->string('bharti_axa',100)->nullable()->default('NULL');
		$table->string('cholamandalam',100)->nullable()->default('NULL');
		$table->string('dhfl',100)->nullable()->default('NULL');
		$table->string('edelweiss',100)->nullable()->default('NULL');
		$table->string('future_generali',50)->nullable()->default('NULL');
		$table->string('godigit',50)->nullable()->default('NULL');
		$table->string('hdfc_ergo',100)->nullable()->default('NULL');
		$table->string('hdfc',100)->nullable()->default('NULL');
		$table->string('hdfc_ergo_gic',100)->nullable()->default('NULL');
		$table->string('icici_lombard',100)->nullable()->default('NULL');
		$table->string('iffco_tokio',100)->nullable()->default('NULL');
		$table->string('kotak',100)->nullable()->default('NULL');
		$table->string('liberty_videocon',100)->nullable()->default('NULL');
		$table->string('magma',100)->nullable()->default('NULL');
		$table->string('national_insurance',100)->nullable()->default('NULL');
		$table->string('raheja',50)->nullable()->default('NULL');
		$table->string('reliance',50)->nullable()->default('NULL');
		$table->string('royal_sundaram',50)->nullable()->default('NULL');
		$table->string('shriram',100)->nullable()->default('NULL');
		$table->string('tata_aig',100)->nullable()->default('NULL');
		$table->string('united_india',100)->nullable()->default('NULL');
		$table->string('universal_sompo',100)->nullable()->default('NULL');
		$table->string('new_india',100)->nullable()->default('NULL');
		$table->string('nic',50)->nullable()->default('NULL');

        });
    }

    public function down()
    {
        Schema::dropIfExists('previous_insurer_mappping');
    }
}