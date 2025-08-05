<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeyPaymentRequestResponseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payment_request_response', function (Blueprint $table) {
            $table->bigInteger('user_product_journey_id')->change();
            $table->integer('user_proposal_id')->change();
            $table->foreign('user_product_journey_id')->references('user_product_journey_id')->on('user_product_journey')->onUpdate('NO ACTION')->onDelete('NO ACTION');
           $table->foreign('user_proposal_id')->references('user_proposal_id')->on('user_proposal')->onUpdate('NO ACTION')->onDelete('NO ACTION');

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
            //
        });
    }
}
