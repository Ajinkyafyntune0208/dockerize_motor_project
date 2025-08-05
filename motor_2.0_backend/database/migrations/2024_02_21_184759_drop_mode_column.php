<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropModeColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('finsall_transaction_data')) {
            Schema::table('finsall_transaction_data', function (Blueprint $table) {
                $table->dropColumn('mode');
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
        if (Schema::hasTable('finsall_transaction_data')) {
            Schema::table('finsall_transaction_data', function (Blueprint $table) {
                //
            });
        }
    }
}
