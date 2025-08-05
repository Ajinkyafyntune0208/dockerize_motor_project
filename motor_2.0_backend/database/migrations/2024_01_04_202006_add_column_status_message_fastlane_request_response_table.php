<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnStatusMessageFastlaneRequestResponseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('fastlane_request_response', 'status')) {
            Schema::table('fastlane_request_response', function (Blueprint $table) {
                $table->string('status', 15)->after('response')->nullable();
                $table->text('message')->after('status')->nullable();
                $table->index('status');
                if (!Schema::hasColumn('fastlane_request_response', 'type')) {
                    $table->string('type', 20)->after('transaction_type')->nullable();
                    $table->index('type');
                }
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
        if (Schema::hasColumn('fastlane_request_response', 'status')) {
            Schema::table('fastlane_request_response', function (Blueprint $table) {
                $table->dropColumn('status');
                $table->dropColumn('message');
                if (Schema::hasColumn('fastlane_request_response', 'type')) {
                    $table->dropColumn('type');
                }
            });
        }
    }
}
