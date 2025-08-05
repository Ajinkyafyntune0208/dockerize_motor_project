<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddToastShownColumnInCorporateVehiclesQuotesRequest extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
            $table->enum('is_toast_shown',['Y','N'])->nullable()->default('N');
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
            $table->dropColumn('is_toast_shown');
        });
    }
}
