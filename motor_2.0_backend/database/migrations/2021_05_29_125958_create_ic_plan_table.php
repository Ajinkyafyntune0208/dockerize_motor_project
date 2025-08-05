<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIcPlanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ic_plan', function (Blueprint $table) {
            $table->integer('ic_plan_id', true);
            $table->string('plan_name', 50)->nullable();
            $table->string('plan_code', 50)->nullable();
            $table->integer('product_type_id')->nullable();
            $table->integer('product_sub_type_id')->nullable();
            $table->integer('ic_id')->nullable();
            $table->string('plan_type')->default('Offline');
            $table->string('status')->nullable()->default('Active');
            $table->integer('created_by')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('updated_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ic_plan');
    }
}
