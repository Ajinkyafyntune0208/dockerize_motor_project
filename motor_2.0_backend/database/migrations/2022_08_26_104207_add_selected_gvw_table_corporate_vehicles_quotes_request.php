<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSelectedGvwTableCorporateVehiclesQuotesRequest extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('corporate_vehicles_quotes_request', 'selected_gvw')) //check the column
        {
            Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
                $table->Integer('selected_gvw')->nullable();
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
