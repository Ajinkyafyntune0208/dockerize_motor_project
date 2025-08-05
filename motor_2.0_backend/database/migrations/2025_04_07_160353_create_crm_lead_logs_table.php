<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCrmLeadLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('crm_lead_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_product_journey_id')->nullable()->index();
            $table->string('url')->nullable();
            $table->longText('request')->nullable();
            $table->longText('response')->nullable();
            $table->string('type', 100)->index();
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
        Schema::dropIfExists('crm_lead_logs');
    }
}
