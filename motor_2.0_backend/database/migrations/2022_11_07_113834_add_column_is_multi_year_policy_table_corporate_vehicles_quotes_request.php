<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIsMultiYearPolicyTableCorporateVehiclesQuotesRequest extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('corporate_vehicles_quotes_request', 'is_multi_year_policy')) {
            Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
                $table->text('is_multi_year_policy')->nullable()->after('previous_policy_type_identifier');
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
