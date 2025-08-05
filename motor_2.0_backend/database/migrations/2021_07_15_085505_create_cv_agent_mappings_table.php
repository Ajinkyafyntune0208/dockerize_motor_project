<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCvAgentMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cv_agent_mappings', function (Blueprint $table) {
            $table->id();
            $table->integer('user_product_journey_id')->nullable();
            $table->integer('user_proposal_id')->nullable();
            $table->string('stage')->nullable();
            $table->unsignedInteger('ic_id')->nullable();
            $table->string('ic_name')->nullable();
            $table->enum('seller_type', ['E', 'P', 'U'])->nullable();
            $table->integer('agent_id')->nullable();
            $table->string('agent_name')->nullable();
            $table->string('agent_mobile')->nullable();
            $table->string('agent_email')->nullable();
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
        Schema::dropIfExists('cv_agent_mappings');
    }
}
