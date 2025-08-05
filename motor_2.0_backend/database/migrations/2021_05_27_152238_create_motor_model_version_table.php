<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMotorModelVersionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('motor_model_version', function (Blueprint $table) {
            $table->increments('version_id');
            $table->unsignedInteger('model_id')->index('FK_MI_ModelVersion_MI_Model');
            $table->unsignedInteger('segment_id')->nullable();
            $table->string('version_name', 200);
            $table->decimal('cubic_capacity', 5, 0)->comment('CC');
            $table->integer('grosss_vehicle_weight')->nullable();
            $table->string('bodytype', 50)->nullable();
            $table->string('carrying_capicity', 50);
            $table->string('age_min', 50)->nullable();
            $table->string('age_max', 50)->nullable();
            $table->string('showroom_price', 50);
            $table->boolean('is_discontinued')->default(0);
            $table->string('is_discontinued_date', 50)->nullable();
            $table->string('fuel_type', 50);
            $table->string('seating_capacity', 50);
            $table->string('year', 50)->nullable();
            $table->integer('created_by');
            $table->string('created_date', 50)->nullable();
            $table->integer('updated_by')->nullable();
            $table->string('updated_date', 50)->nullable();
            $table->integer('deleted_by')->nullable();
            $table->string('deleted_date', 50)->nullable();
            $table->string('status')->default('Active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('motor_model_version');
    }
}
