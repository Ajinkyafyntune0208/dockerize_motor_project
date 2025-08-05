<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PaymentCheckStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('payment_check_status')) 
        {
            Schema::create('payment_check_status', function (Blueprint $table) 
            {
                $table->id();
                $table->integer('payment_request_response_id')->nullable();
                $table->integer('user_product_journey_id')->nullable();
                $table->integer('proposal_id')->nullable();
                $table->integer('ic_id')->nullable();
                $table->string('order_id')->nullable();
                $table->string('existing_payment_status')->nullable();
                $table->string('updated_payment_status')->nullable();
                $table->string('existing_journey_stage')->nullable();
                $table->string('updated_journey_stage')->nullable();
                $table->text('required_payment_data')->nullable();
                $table->text('pdf_status')->nullable();
                $table->text('response')->nullable();
                $table->dateTime('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
