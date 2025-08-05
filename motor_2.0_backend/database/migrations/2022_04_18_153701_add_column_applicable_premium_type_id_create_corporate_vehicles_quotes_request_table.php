<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnApplicablePremiumTypeIdCreateCorporateVehiclesQuotesRequestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('corporate_vehicles_quotes_request', 'applicable_premium_type_id'))
        {
            Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
                $table->integer('applicable_premium_type_id')->nullable();
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
