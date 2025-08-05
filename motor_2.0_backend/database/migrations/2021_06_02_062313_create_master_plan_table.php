<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterPlanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_plan', function (Blueprint $table) {
            $table->integer('plan_id', true);
            $table->integer('insu_id')->default(0);
            $table->integer('policy_id')->index('fk_policy_id');
            $table->string('plan_name');
            $table->string('plan_description');
            $table->string('status')->default('Active');
            $table->integer('created_by');
            $table->dateTime('created_date')->useCurrent();
            $table->integer('updated_by')->nullable();
            $table->dateTime('updated_date')->nullable();
            $table->dateTime('deleted_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_plan');
    }
}
