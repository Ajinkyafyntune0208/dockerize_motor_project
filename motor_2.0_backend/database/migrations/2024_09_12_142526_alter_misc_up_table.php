<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterMiscUpTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('misc_usp')) {
            if (Schema::hasColumn('misc_usp', 'id')) {
                Schema::table('misc_usp', function (Blueprint $table) {
                    $table->dropColumn('id');
                });
            }

            if (!Schema::hasColumn('misc_usp', 'misc_usp_id')) {
                Schema::table('misc_usp', function (Blueprint $table) {
                    $table->integer('misc_usp_id')->first()->primary();
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
