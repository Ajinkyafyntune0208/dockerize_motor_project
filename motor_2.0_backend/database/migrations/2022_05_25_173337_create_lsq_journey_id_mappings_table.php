<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLsqJourneyIdMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ( ! Schema::hasTable('lsq_journey_id_mappings'))
        {
            Schema::create('lsq_journey_id_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('enquiry_id')->nullable();
                $table->string('lead_id')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lsq_journey_id_mappings');
    }
}
