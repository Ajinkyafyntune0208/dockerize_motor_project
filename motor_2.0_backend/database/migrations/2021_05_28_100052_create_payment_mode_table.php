<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentModeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_mode', function (Blueprint $table) {
            $table->integer('payment_mode_id', true);
            $table->integer('user_id');
            $table->string('payment_mode', 50)->nullable();
            $table->string('account_no', 50)->nullable();
            $table->integer('payment_limit')->nullable();
            $table->string('payment_status')->nullable()->default('Active');
            $table->integer('credit_days')->nullable();
            $table->dateTime('created_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_mode');
    }
}
