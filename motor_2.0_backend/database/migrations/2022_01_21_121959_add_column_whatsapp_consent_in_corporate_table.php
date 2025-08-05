<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnWhatsappConsentInCorporateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
            $table->string('whatsapp_consent')->nullable();
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
            $table->dropColumn('whatsapp_consent');
        });
    }
}
