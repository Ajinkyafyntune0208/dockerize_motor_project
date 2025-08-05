<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PaymentPendingStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('payment_pending_status')) 
        {
            Schema::create('payment_pending_status', function (Blueprint $table) 
            {
                $table->id();
                $table->bigInteger('enquiry_id')->nullable();
                $table->integer('payment_request_response_id')->nullable();
                $table->integer('user_product_journey_id')->nullable();
                $table->integer('proposal_id')->nullable();
                $table->integer('ic_id')->nullable();
                $table->string('order_id')->nullable();
                $table->string('existing_payment_status')->nullable();
                $table->string('updated_payment_status')->nullable();
                $table->string('existing_journey_stage')->nullable();
                $table->string('updated_journey_stage')->nullable();
                $table->string('existing_policy_number')->nullable();
                $table->string('updated_policy_number')->nullable();
                $table->text('pdf_link')->nullable();
                $table->longText('payment_status_response')->nullable();
                $table->longText('policy_status_response')->nullable();
                $table->longText('pdf_response')->nullable();
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
