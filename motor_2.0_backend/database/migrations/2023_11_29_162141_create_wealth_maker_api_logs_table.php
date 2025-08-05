<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWealthMakerApiLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wealth_maker_api_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_product_journey_id')->nullable();
            $table->bigInteger('renewal_data_migration_status_id')->nullable();
            $table->text('request')->nullable();
            $table->text('response')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->index('user_product_journey_id');
            $table->index('renewal_data_migration_status_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wealth_maker_api_logs');
    }
}
