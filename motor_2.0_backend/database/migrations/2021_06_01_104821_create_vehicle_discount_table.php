<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehicleDiscountTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicle_discount', function (Blueprint $table) {
            $table->integer('discount_id', true);
            $table->string('schema_name', 50);
            $table->integer('segment_type_id')->nullable();
            $table->integer('manf_id')->nullable();
            $table->integer('model_id')->nullable();
            $table->integer('version_id')->nullable();
            $table->date('schema_valid_from');
            $table->date('schema_valid_to');
            $table->integer('master_policy_id')->nullable();
            $table->integer('product_sub_type_id')->nullable();
            $table->integer('segment_id')->nullable();
            $table->integer('rto_cluster_id')->nullable();
            $table->integer('age_min')->nullable();
            $table->integer('age_max')->nullable();
            $table->integer('discount_rate')->nullable();
            $table->string('metric', 20)->nullable();
            $table->string('ncb_option', 20)->nullable();
            $table->string('status')->default('Active');
            $table->integer('created_by')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('updated_date')->nullable()->useCurrent();
            $table->string('vehicle_uses', 50)->nullable();
            $table->string('fuel_type', 50);
            $table->string('discount_for', 50);
            $table->integer('ncb_min_discount')->nullable();
            $table->integer('ncb_max_discount')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vehicle_discount');
    }
}
