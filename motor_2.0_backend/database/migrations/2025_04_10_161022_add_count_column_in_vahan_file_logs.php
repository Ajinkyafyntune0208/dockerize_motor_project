<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCountColumnInVahanFileLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('vahan_file_logs', 'total_count')) {
            Schema::table('vahan_file_logs', function (Blueprint $table) {
                $table->integer('total_count')->after('file_name')->default(0);
            });
        }
        if (!Schema::hasColumn('vahan_file_logs', 'processed_count')) {
            Schema::table('vahan_file_logs', function (Blueprint $table) {
                $table->integer('processed_count')->after('total_count')->default(0);
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
        Schema::table('vahan_file_logs', function (Blueprint $table) {
            //
        });
    }
}
