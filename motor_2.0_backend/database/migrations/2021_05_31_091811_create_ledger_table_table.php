<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLedgerTableTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ledger_table', function (Blueprint $table) {
            $table->integer('ledger_id', true);
            $table->integer('user_id');
            $table->integer('user_for');
            $table->integer('user_type_id');
            $table->integer('proposal_detail_id');
            $table->bigInteger('account_no')->default(0);
            $table->string('proposal_no', 50)->nullable();
            $table->string('policy_no', 50);
            $table->integer('amount');
            $table->integer('available_balance');
            $table->dateTime('transaction_date');
            $table->string('transaction_type', 50);
            $table->string('transaction_remark', 50);
            $table->integer('created_by');
            $table->dateTime('created_on')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ledger_table');
    }
}
