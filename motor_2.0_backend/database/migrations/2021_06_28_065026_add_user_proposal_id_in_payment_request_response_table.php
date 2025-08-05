<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserProposalIdInPaymentRequestResponseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payment_request_response', function (Blueprint $table) {
            $table->integer('user_proposal_id')->nullable()->after('user_product_journey_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payment_request_response', function (Blueprint $table) {
            $table->dropColumn('user_proposal_id'); 
        });
    }
}
