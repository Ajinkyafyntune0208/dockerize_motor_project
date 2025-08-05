<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnPreviousMasterPolicyIdPreviousProductIdentifierTableCorporateVehiclesQuotesRequest extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('corporate_vehicles_quotes_request', 'previous_master_policy_id'))
        {
            Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
                $table->integer('previous_master_policy_id')->nullable();
            });            
        } 
        if (!Schema::hasColumn('corporate_vehicles_quotes_request', 'previous_product_identifier'))
        {
            Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
                $table->string('previous_product_identifier')->nullable();
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
