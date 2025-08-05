<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuoteLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quote_log', function (Blueprint $table) {
            $table->bigInteger('quote_id', true);
            $table->integer('corp_id')->nullable();
            $table->integer('user_product_journey_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('product_sub_type_id')->nullable();
            $table->bigInteger('quotes_request_id')->nullable();
            $table->text('quote_data');
            $table->longText('quote_response')->nullable();
            $table->longText('source')->nullable();
            $table->dateTime('searched_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->integer('ex_showroom_price_idv')->nullable();
            $table->integer('final_premium_amount')->nullable();
            $table->integer('master_policy_id')->nullable();
            $table->longText('premium_json')->nullable();
            $table->integer('is_selected')->nullable();
            $table->boolean('status')->nullable()->default(0);
            $table->integer('od_premium')->nullable();
            $table->integer('tp_premium')->nullable();
            $table->integer('service_tax')->nullable();
            $table->integer('addon_premium')->nullable();
            $table->string('is_discount_change', 10)->nullable();
            $table->integer('change_default_discount')->nullable();
            $table->string('change_discount_status', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('quote_log');
    }
}
