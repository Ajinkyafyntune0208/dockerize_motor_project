<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnInVahanFileLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('vahan_file_logs', 'exisiting_count')) {
            Schema::table('vahan_file_logs', function (Blueprint $table) {
                $table->integer('exisiting_count')->after('processed_count')->default(0);
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
