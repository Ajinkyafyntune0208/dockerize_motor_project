<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIsDuplicateInLsqJourneyIdMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lsq_journey_id_mappings', function (Blueprint $table) {
            if ( ! Schema::hasColumn('lsq_journey_id_mappings', 'is_duplicate'))
            {
                $table->integer('is_duplicate')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lsq_journey_id_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('lsq_journey_id_mappings', 'is_duplicate'))
            {
                $table->dropColumn('is_duplicate');
            }
        });
    }
}
