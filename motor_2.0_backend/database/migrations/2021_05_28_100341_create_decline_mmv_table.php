<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeclineMmvTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('decline_mmv', function (Blueprint $table) {
            $table->integer('decline_mmv_id', true);
            $table->string('decline_by', 20)->nullable();
            $table->integer('decline_by_mmv_id')->nullable();
            $table->integer('manf_id');
            $table->integer('model_id');
            $table->integer('version_id');
            $table->integer('corp_id');
            $table->integer('master_policy_id');
            $table->string('status', 20);
            $table->integer('created_by');
            $table->integer('updated_by')->nullable();
            $table->dateTime('created_date')->useCurrent();
            $table->dateTime('updated_date')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('decline_mmv');
    }
}
