<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentRequestResponseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_request_response', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('quote_id')->nullable();
            $table->integer('user_product_journey_id')->nullable();
            $table->integer('ic_id')->nullable();
            $table->string('payment_url', 256)->nullable();
            $table->string('return_url', 256)->nullable();
            $table->longText('order_id')->nullable();
            $table->string('amount', 100)->nullable();
            $table->string('customer_id', 20)->nullable();
            $table->string('proposal_no', 50)->nullable();
            $table->longText('response')->nullable();
            $table->string('status', 256)->nullable();
            $table->string('lead_source', 256)->nullable();
            $table->integer('active')->nullable()->default(0);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_request_response');
    }
}
