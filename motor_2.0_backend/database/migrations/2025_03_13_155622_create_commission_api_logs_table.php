<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommissionApiLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('commission_api_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_product_journey_id');
            $table->string('url')->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->enum('type', ['PAYIN', 'PAYOUT']);
            $table->string('transaction_type', 30)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('commission_api_logs');
    }
}
