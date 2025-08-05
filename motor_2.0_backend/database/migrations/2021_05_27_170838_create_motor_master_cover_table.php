<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMotorMasterCoverTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('motor_master_cover', function (Blueprint $table) {
            $table->integer('motor_cover_id', true);
            $table->integer('plan_id')->index('fk_plan_id');
            $table->integer('sub_product_id');
            $table->integer('min_age')->default(0);
            $table->integer('max_age')->default(0);
            $table->integer('min_cc')->default(0);
            $table->integer('max_cc')->default(0);
            $table->integer('segment_type_id')->default(0);
            $table->integer('vehicle_id')->nullable()->index('fk_vehicle_id');
            $table->integer('si_list')->nullable();
            $table->string('cover_type');
            $table->double('min_rate')->default(0);
            $table->double('max_rate')->default(0);
            $table->string('abs_rate_flag', 10)->comment('flag');
            $table->string('metric', 20)->nullable();
            $table->string('is_mandatory')->default('Y');
            $table->string('cover_order', 50)->default('0');
            $table->string('cover_name');
            $table->string('cover_description')->nullable();
            $table->string('section_name')->nullable();
            $table->string('cover_print_name', 100)->nullable();
            $table->string('status')->default('Active');
            $table->string('addon_cover_apply_on', 50);
            $table->string('created_by', 50);
            $table->dateTime('created_date')->useCurrent();
            $table->string('updated_by', 50)->nullable();
            $table->dateTime('updated_date')->nullable();
            $table->string('deleted_by', 50)->nullable();
            $table->dateTime('deleted_date')->nullable();
            $table->string('addon_in_prev_policy', 10)->nullable();
            $table->integer('addon_applicable_up_to')->nullable();
            $table->integer('cover_type_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('motor_master_cover');
    }
}
