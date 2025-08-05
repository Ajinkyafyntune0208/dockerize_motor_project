<?php

use App\Models\ErrorVisibilityReport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsInErrorVisibilityReports extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('error_visibility_reports')) {
            Schema::table('error_visibility_reports', function (Blueprint $table) {
                $table->integer('total_response_time')->after('failure');
                $table->integer('success_response_time')->after('total_response_time');
                $table->integer('failure_response_time')->after('success_response_time');
            });
            // Need to clear old records as new columns are introduced.
            ErrorVisibilityReport::truncate();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('error_visibility_reports')) {
            Schema::table('error_visibility_reports', function (Blueprint $table) {
                $table->dropColumn('total_response_time');
                $table->dropColumn('success_response_time');
                $table->dropColumn('failure_response_time');
            });
        }
    }
}
