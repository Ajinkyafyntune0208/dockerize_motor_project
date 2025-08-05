<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_details', function (Blueprint $table) {
            $table->integer('payment_detail_id', true);
            $table->integer('proposal_id')->nullable();
            $table->integer('fk_quote_id')->nullable();
            $table->string('payment_from', 50)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('payment_mode', 50)->nullable();
            $table->string('amount', 50)->nullable();
            $table->string('cheque_or_dd_no', 50)->nullable();
            $table->date('cheque_or_dd_date')->nullable();
            $table->string('transaction_no', 50)->nullable();
            $table->date('date_of_transaction')->nullable();
            $table->string('status')->nullable()->default('Pending');
            $table->string('payment_status')->nullable()->default('Payment-Pending');
            $table->string('payment_save_date', 50)->nullable();
            $table->string('payee_name', 200)->nullable();
            $table->dateTime('created_date')->useCurrent();
            $table->integer('created_by')->nullable();
            $table->string('bank_name', 50)->nullable();
            $table->string('check_no', 50)->nullable();
            $table->string('bank_ifsc', 50)->nullable();
            $table->string('branch_name', 50)->nullable();
            $table->string('date_of_check', 50)->nullable();
            $table->integer('endorsement_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_details');
    }
}
