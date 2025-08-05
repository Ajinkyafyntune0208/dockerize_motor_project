<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicywiseSegmentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('policywise_segment', function (Blueprint $table) {
            $table->bigInteger('policywise_segment_id', true);
            $table->bigInteger('master_segment_id')->nullable();
            $table->string('segment_name', 50)->nullable();
            $table->integer('manf_id')->nullable();
            $table->integer('model_id')->nullable();
            $table->integer('version_id')->nullable();
            $table->integer('discount_percentage')->nullable();
            $table->integer('created_by')->nullable();
            $table->dateTime('created_on')->nullable();
            $table->string('status')->nullable()->default('Active');
            $table->integer('segment_type_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('policywise_segment');
    }
}
