<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTableAddFulltextToUnitedIndiaFinancierMaster extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ((Schema::hasTable('united_india_financier_master'))){
            Schema::table('united_india_financier_master', function (Blueprint $table) {
                $table->fullText('financier_code');
                $table->fullText('financier_name');
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
        Schema::table('united_india_financier_master', function (Blueprint $table) {
            //
        });
    }
}
