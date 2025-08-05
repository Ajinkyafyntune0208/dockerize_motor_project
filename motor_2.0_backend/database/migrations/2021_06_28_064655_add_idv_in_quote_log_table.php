<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdvInQuoteLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quote_log', function (Blueprint $table) {
            $table->integer('idv')->nullable()->after('ex_showroom_price_idv');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quote_log', function (Blueprint $table) {
            $table->dropColumn('idv');
        });
    }
}
