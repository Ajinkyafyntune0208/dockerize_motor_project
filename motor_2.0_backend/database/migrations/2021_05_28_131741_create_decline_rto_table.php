<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeclineRtoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('decline_rto', function (Blueprint $table) {
            $table->integer('decline_rto_id', true);
            $table->string('rto_number', 11);
            $table->integer('master_policy_id');
            $table->integer('corp_id');
            $table->string('status', 11);
            $table->integer('created_by')->nullable();
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
        Schema::dropIfExists('decline_rto');
    }
}
