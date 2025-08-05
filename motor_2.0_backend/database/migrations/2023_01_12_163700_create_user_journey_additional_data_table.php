<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserJourneyAdditionalDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_journey_additional_data', function (Blueprint $table) {
            $table->id();
            $table->integer('enquiry_id');
            $table->string('company_alias', 50);
            $table->string('unique_value', 255);
            $table->timestamps();
            $table->index(['enquiry_id', 'company_alias']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_journey_additional_data');
    }
}
