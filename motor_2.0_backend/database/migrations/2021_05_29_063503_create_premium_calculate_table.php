<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePremiumCalculateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('premium_calculate', function (Blueprint $table) {
            $table->integer('premium_calculate_id', true);
            $table->integer('premium_calculate_bulk_upload_id');
            $table->integer('quotes_id')->default(0);
            $table->string('vehicle_registration_no', 50)->nullable();
            $table->string('chassis_no', 50)->nullable();
            $table->string('engine_no', 50)->nullable();
            $table->string('previous_policy_number', 50)->nullable();
            $table->longText('premium_json')->nullable();
            $table->string('policy_type', 50)->nullable();
            $table->string('premium_amount', 50);
            $table->dateTime('created_date')->nullable()->useCurrent();
            $table->string('status')->nullable()->default('Y');
            $table->dateTime('updated_date')->nullable()->useCurrent();
            $table->integer('user_defined_discount')->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('premium_calculate');
    }
}
