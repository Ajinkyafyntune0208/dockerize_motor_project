<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewColumnRevisedNcbTableQuoteLog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quote_log', function (Blueprint $table) {
            $table->integer('revised_ncb')->nullable()
                  ->after('addon_premium');
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
            $table->dropColumn('revised_ncb');
        });
    }
}
