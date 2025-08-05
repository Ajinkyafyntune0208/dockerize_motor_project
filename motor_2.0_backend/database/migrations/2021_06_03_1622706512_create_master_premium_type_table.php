<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterPremiumTypeTable extends Migration
{
    public function up()
    {
        Schema::create('master_premium_type', function (Blueprint $table) {

		$table->id();
		$table->string('premium_type',50)->default('');
		$table->string('premium_type_code',50)->default('');
		$table->enum('status',['Y','N'])->default('Y');
		$table->timestamps();

        });
    }

    public function down()
    {
        Schema::dropIfExists('master_premium_type');
    }
}