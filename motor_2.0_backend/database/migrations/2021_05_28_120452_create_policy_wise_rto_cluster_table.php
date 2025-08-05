<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyWiseRtoClusterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('policy_wise_rto_cluster', function (Blueprint $table) {
            $table->integer('policy_wise_rto_id', true);
            $table->integer('cluster_id')->nullable();
            $table->string('rto_cluster_name', 50);
            $table->integer('cluster_type');
            $table->integer('country_id')->nullable();
            $table->integer('state_id')->nullable();
            $table->integer('city_id')->nullable();
            $table->string('status')->nullable()->default('Active');
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
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
        Schema::dropIfExists('policy_wise_rto_cluster');
    }
}
