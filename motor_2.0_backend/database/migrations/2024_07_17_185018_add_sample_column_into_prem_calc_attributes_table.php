<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSampleColumnIntoPremCalcAttributesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('prem_calc_attributes')) {
            if (!Schema::hasColumn('prem_calc_attributes', 'sample_value')) {
                Schema::table('prem_calc_attributes', function (Blueprint $table) {
                    $table->string('sample_value', 500)->after('attribute_trail')->nullable();
                });
            }
            if (!Schema::hasColumn('prem_calc_attributes', 'sample_type')) {
                Schema::table('prem_calc_attributes', function (Blueprint $table) {
                    $table->string('sample_type', 50)->after('sample_value')->nullable();
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
    }
}
