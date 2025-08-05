<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterRtoClusterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_rto_cluster', function (Blueprint $table) {
            $table->integer('rto_group_id', true);
            $table->integer('master_policy_id');
            $table->string('rto_group_name');
            $table->string('short_name');
            $table->string('status')->default('Active');
            $table->integer('created_by');
            $table->dateTime('created_date');
            $table->integer('updated_by')->nullable();
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
        Schema::dropIfExists('master_rto_cluster');
    }
}
