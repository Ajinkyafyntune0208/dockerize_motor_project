<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCvApplicableAddonsTable extends Migration
{
    public function up()
    {
        Schema::create('cv_applicable_addons', function (Blueprint $table) {

		$table->unsignedBigInteger('addon_id');
		$table->string('addon_name')->nullable();
		$table->integer('ic_id',)->nullable();
		$table->string('ic_alias')->nullable();
		$table->string('addon_age')->nullable();
		$table->enum('is_applicable',['Y','N'])->nullable()->default('Y');
        $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cv_applicable_addons');
    }
}