<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommonTransactionDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('common_transaction_detail', function (Blueprint $table) {
            $table->integer('c_id', true);
            $table->integer('temp_id');
            $table->string('content');
            $table->string('status', 50);
            $table->integer('sender_id');
            $table->string('sender_name');
            $table->string('to');
            $table->string('ip_address', 200);
            $table->dateTime('delivery_date');
            $table->dateTime('json_content');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('common_transaction_detail');
    }
}
