<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsDefaultCoverChangeInCorporateVehicleQuoteRequestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('corporate_vehicle_quote_request', function (Blueprint $table) {
            if (!Schema::hasColumn('corporate_vehicles_quotes_request', 'is_default_cover_changed'))
            {
                Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
                    $table->enum('is_default_cover_changed',['Y','N'])->nullable()->default('N');
                });            
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
        Schema::table('corporate_vehicle_quote_request', function (Blueprint $table) {
            $table->dropIfExists('is_default_cover_changed');
        });
    }
}
