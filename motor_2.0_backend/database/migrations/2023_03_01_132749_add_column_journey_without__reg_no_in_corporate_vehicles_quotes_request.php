<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnJourneyWithoutRegNoInCorporateVehiclesQuotesRequest extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
            if(!Schema::hasColumn('corporate_vehicles_quotes_request', 'journey_without_regno')) {
                $table->string('journey_without_regno',20)->default('NULL');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
            if(Schema::hasColumn('corporate_vehicles_quotes_request', 'journey_without_regno')) {
                $table->dropColumn('journey_without_regno');
            }
        });
    }
}
