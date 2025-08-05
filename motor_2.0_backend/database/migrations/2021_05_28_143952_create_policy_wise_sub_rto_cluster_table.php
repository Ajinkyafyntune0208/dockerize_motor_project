<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyWiseSubRtoClusterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('policy_wise_sub_rto_cluster', function (Blueprint $table) {
            $table->integer('sub_cluster_id', true);
            $table->integer('rto_group_id')->nullable();
            $table->integer('cluster_group_id')->nullable();
            $table->string('sub_cluster_name', 50)->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->string('status')->nullable()->default('Active');
            $table->dateTime('created_date')->nullable()->useCurrent();
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
        Schema::dropIfExists('policy_wise_sub_rto_cluster');
    }
}
