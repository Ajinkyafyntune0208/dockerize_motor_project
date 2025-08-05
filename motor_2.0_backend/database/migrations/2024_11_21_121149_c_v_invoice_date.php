<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CVInvoiceDate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        if(!Schema::hasColumn('corporate_vehicles_quotes_request', 'vehicle_invoice_date')) {
            
            Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
                $table->string('vehicle_invoice_date',50)->nullable();
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
        Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
            $table->dropColumn('vehicle_invoice_date');
        });
    }
}
