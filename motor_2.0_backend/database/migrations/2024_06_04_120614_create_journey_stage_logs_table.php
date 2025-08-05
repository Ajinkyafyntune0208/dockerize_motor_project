<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJourneyStageLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('journey_stage_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_product_journey_id')->nullable()->index();
            $table->string('previous_stage', 255)->nullable();
            $table->string('current_stage', 255)->nullable();
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
        Schema::dropIfExists('journey_stage_logs');
    }
}
