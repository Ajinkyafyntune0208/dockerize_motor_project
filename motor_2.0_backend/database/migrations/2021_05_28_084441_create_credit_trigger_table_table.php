<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCreditTriggerTableTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('credit_trigger_table', function (Blueprint $table) {
            $table->integer('trigger_id', true);
            $table->integer('user_id');
            $table->string('account_no', 50);
            $table->integer('proposal_no_or_policy_no');
            $table->dateTime('date_of_creation');
            $table->dateTime('paid_date');
            $table->string('flag_paid_or_unpaid', 50);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('credit_trigger_table');
    }
}
