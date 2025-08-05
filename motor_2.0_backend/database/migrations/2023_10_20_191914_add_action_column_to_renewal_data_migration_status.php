<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddActionColumnToRenewalDataMigrationStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('renewal_data_migration_statuses', function (Blueprint $table) {
            $table->string('action')->nullable()->after('attempts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('renewal_data_migration_statuses', function (Blueprint $table) {
            $table->dropColumn('action');
        });
    }
}
