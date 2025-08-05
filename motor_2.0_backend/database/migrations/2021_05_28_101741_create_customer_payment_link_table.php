<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerPaymentLinkTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_payment_link', function (Blueprint $table) {
            $table->bigInteger('payment_link_id', true);
            $table->bigInteger('proposal_id')->nullable();
            $table->string('link_url', 6000);
            $table->string('link_expiry_date', 20)->nullable();
            $table->string('status')->nullable()->default('Y');
            $table->bigInteger('encrypt_quote_id')->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->dateTime('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customer_payment_link');
    }
}
