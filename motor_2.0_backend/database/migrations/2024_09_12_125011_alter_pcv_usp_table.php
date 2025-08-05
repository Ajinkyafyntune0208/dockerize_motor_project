<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterPcvUspTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('pcv_usp')) {
            if (Schema::hasColumn('pcv_usp', 'gcv_usp_id')) {
                Schema::table('pcv_usp', function (Blueprint $table) {
                    $table->dropColumn('gcv_usp_id');
                });
            }

            if (!Schema::hasColumn('pcv_usp', 'pcv_usp_id')) {
                Schema::table('pcv_usp', function (Blueprint $table) {
                    $table->integer('pcv_usp_id')->first()->primary();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
