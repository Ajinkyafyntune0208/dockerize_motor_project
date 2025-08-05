<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJourneyCampaignDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('journey_campaign_details', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_product_journey_id');
            $table->string('utm_source',100)->nullable();
            $table->string('utm_medium',100)->nullable();
            $table->string('utm_campaign',100)->nullable();
            $table->string('lead_source',100)->nullable();
            $table->timestamps();

            $table->index('user_product_journey_id');
        });
        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('journey_campaign_details');
    }
}
