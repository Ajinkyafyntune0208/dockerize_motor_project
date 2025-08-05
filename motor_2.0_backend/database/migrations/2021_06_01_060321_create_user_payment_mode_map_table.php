<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserPaymentModeMapTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_payment_mode_map', function (Blueprint $table) {
            $table->integer('user_payment_mode_map_id', true);
            $table->integer('user_id');
            $table->integer('mode_id');
            $table->integer('corp_id');
            $table->bigInteger('cd_account_no')->default(0);
            $table->bigInteger('cdt_account_no')->default(0);
            $table->integer('cd_amount');
            $table->integer('cdt_amount');
            $table->integer('cd_limit');
            $table->integer('cdt_limit');
            $table->integer('cdt_days');
            $table->integer('available_balance');
            $table->string('status')->nullable();
            $table->integer('updated_by')->nullable();
            $table->integer('created_by')->nullable();
            $table->dateTime('updated_date')->nullable();
            $table->dateTime('created_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_payment_mode_map');
    }
}
