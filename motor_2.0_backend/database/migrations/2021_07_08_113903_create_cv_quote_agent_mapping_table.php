<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCvQuoteAgentMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cv_quote_agent_mapping', function (Blueprint $table) {
            $table->integer('cv_quote_agent_mapping_id', true);
            $table->integer('user_product_journey_id')->nullable();
            $table->enum('seller_type', ['E', 'P'])->nullable();
            $table->integer('agent_id')->nullable();
            $table->string('agent_name', 100)->nullable();
            $table->string('agent_mobile', 50)->nullable();
            $table->string('agent_email', 100)->nullable();
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
        Schema::dropIfExists('cv_quote_agent_mapping');
    }
}
