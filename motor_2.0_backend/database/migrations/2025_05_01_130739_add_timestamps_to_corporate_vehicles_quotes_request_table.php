<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTimestampsToCorporateVehiclesQuotesRequestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
            if (!Schema::hasColumn('quote_log', 'created_at') && !Schema::hasColumn('quote_log', 'updated_at')) {
                Schema::table('quote_log', function (Blueprint $table) {
                    $table->timestamp('created_at')->nullable()->after('quote_data');
                    $table->timestamp('updated_at')->nullable()->after('created_at');
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
        Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
            //
        });
    }
}
