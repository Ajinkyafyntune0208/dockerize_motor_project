<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnRemoveHeaderFooterTableCorporateVehiclesQuotesRequest extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('corporate_vehicles_quotes_request', 'remove_header_footer')) {
           Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
           $table->char('remove_header_footer', 1)->nullable();
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
