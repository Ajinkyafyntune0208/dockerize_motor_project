<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHdfcErgoV1TransactionNoToPaymentRquestResponseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasColumn('payment_request_response', 'transaction_no')){
            // Schema::table('payment_request_response', function (Blueprint $table) {
            //     $table->string('transaction_no',50)->nullable()->after('proposal_no');;
            // });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if(Schema::hasColumn('payment_request_response', 'transaction_no')){
            // Schema::table('payment_request_response', function (Blueprint $table) {
            //     $table->dropColumn('transaction_no'); 
            // });
        }
    }
}
