<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnCkycNumberInTableQuoteLog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quote_log', function (Blueprint $table) {
            if ( ! Schema::hasColumn('quote_log', 'is_ckyc_verified')) {
                $table->enum('is_ckyc_verified', ['Y', 'N'])->default('N');
            }

            if ( ! Schema::hasColumn('quote_log', 'ckyc_number')) {
                $table->string('ckyc_number', 255)->nullable();
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
        Schema::table('quote_log', function (Blueprint $table) {
            if (Schema::hasColumn('quote_log', 'is_ckyc_verified')) {
                $table->dropColumn('is_ckyc_verified');
            }

            if (Schema::hasColumn('quote_log', 'ckyc_number')) {
                $table->dropColumn('ckyc_number');
            }
        });
    }
}
