<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmailQuoteLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('email_quote_log', function (Blueprint $table) {
            $table->integer('email_quote_log_id', true);
            $table->integer('quote_id')->nullable();
            $table->integer('userid')->nullable();
            $table->integer('user_type_id')->nullable();
            $table->integer('user_product_journey_id')->nullable();
            $table->integer('user_for');
            $table->string('email_id', 50)->nullable();
            $table->string('name', 100)->nullable();
            $table->string('product', 20)->nullable();
            $table->longText('token')->nullable();
            $table->string('policy_type', 11)->nullable();
            $table->longText('link')->nullable();
            $table->longText('send_link_usr')->nullable();
            $table->longText('random_str')->nullable();
            $table->dateTime('created_date')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_quote_log');
    }
}
