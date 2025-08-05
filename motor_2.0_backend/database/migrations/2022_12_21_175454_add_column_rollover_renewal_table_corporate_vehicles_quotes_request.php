<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnRolloverRenewalTableCorporateVehiclesQuotesRequest extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('corporate_vehicles_quotes_request', 'rollover_renewal')) {
            Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
                $table->enum('rollover_renewal', ['Y', 'N'])->nullable()->after('is_renewal');
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
        //
    }
}
