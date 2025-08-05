<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterCorporateVehiclesQuotesRequestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
        //     $table->renameColumn('is_fastlane', 'journey_type');
        // });
        Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
            $table->string('journey_type')->nullable();
            $table->enum('is_renewal',['Y','N'])->default('N');
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
            $table->dropColumn('journey_type');
            $table->dropColumn('is_renewal');
        });
        // Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
        //     $table->enum('is_fastlane', ['Y', 'N'])->default('N')->change();
        // });
    }
}
