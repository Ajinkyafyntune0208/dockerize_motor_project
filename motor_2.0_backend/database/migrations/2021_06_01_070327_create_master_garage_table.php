<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterGarageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_garage', function (Blueprint $table) {
            $table->integer('garage_id', true);
            $table->string('garage_name', 50)->nullable();
            $table->string('contact_number', 15)->nullable();
            $table->string('pincode', 6)->nullable();
            $table->string('address', 500)->nullable();
            $table->integer('city_id')->nullable();
            $table->integer('company_id')->nullable();
            $table->integer('manf_id')->nullable();
            $table->string('status')->nullable()->default('Acive');
            $table->dateTime('created_on')->nullable()->useCurrent();
            $table->string('preferd_garage', 50);
            $table->string('is_smart_cashless')->default('N');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_garage');
    }
}
