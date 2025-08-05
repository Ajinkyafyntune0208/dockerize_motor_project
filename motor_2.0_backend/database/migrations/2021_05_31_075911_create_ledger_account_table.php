<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLedgerAccountTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ledger_account', function (Blueprint $table) {
            $table->bigInteger('ledger_id');
            $table->integer('user_id');
            $table->bigInteger('account_no');
            $table->string('proposal_no', 50)->nullable();
            $table->string('policy_no', 50)->nullable();
            $table->string('amount', 50);
            $table->integer('available_balance')->nullable();
            $table->dateTime('transation_date')->nullable();
            $table->string('transation_type');
            $table->string('transation_remark', 250);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ledger_account');
    }
}
