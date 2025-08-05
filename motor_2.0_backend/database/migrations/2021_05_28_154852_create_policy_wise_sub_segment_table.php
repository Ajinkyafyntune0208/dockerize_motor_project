<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyWiseSubSegmentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('policy_wise_sub_segment', function (Blueprint $table) {
            $table->integer('sub_segment_id', true);
            $table->integer('master_segment_id')->nullable();
            $table->integer('segment_group_id')->nullable();
            $table->string('sub_segment_name', 50)->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->string('status')->nullable()->default('Active');
            $table->dateTime('created_date')->nullable()->useCurrent();
            $table->dateTime('updated_date')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('policy_wise_sub_segment');
    }
}
