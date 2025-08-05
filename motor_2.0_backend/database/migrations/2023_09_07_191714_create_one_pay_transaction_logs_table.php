<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOnePayTransactionLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('one_pay_transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('enquiry_id');
            $table->integer('user_proposal_id');
            $table->integer('ic_id');
            $table->integer('premimum_amount');
            $table->integer('trasaction_id');
            $table->integer('return_url');
            $table->integer('request_data');
            $table->integer('response_data');
            $table->set('status',['PENDING', 'SUCCESS', 'FAILED', 'CANCELLED']);
            $table->timestamps();
            $table->index('enquiry_id');
            $table->index('user_proposal_id');
            $table->index('ic_id');
            $table->index('status');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('one_pay_transaction_logs');
    }
}
