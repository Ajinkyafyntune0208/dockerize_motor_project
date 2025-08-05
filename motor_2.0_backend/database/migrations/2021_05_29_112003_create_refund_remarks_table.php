<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRefundRemarksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('refund_remarks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('refund_id')->nullable()->default(0);
            $table->string('refund_staus_id', 50)->nullable();
            $table->integer('refund_amount')->nullable();
            $table->integer('refund_final_amount')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->string('status', 50)->nullable();
            $table->string('refund_certificate', 250)->nullable();
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
        Schema::dropIfExists('refund_remarks');
    }
}
