<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnTypeInFastlaneRequestResponseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('fastlane_request_response', 'type')) {
            Schema::table('fastlane_request_response', function (Blueprint $table) {
                $table->string('type', 10)->after('transaction_type')->nullable();
                $table->index('type');
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
        if (Schema::hasColumn('fastlane_request_response', 'type')) {
            Schema::table('fastlane_request_response', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
}
