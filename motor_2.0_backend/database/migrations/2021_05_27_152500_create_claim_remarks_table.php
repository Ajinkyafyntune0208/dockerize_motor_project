<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClaimRemarksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('claim_remarks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('claim_id')->default(0);
            $table->string('claim_staus_id', 50)->nullable();
            $table->integer('claim_amount')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->string('status')->nullable()->default('Y');
            $table->string('claim_certificate', 250)->nullable();
            $table->integer('created_by')->nullable();
            $table->dateTime('remark_created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('claim_remarks');
    }
}
