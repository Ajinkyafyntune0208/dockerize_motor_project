<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnLsqSubstageInLsqJourneyIdMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lsq_journey_id_mappings', function (Blueprint $table) {
            if ( ! Schema::hasColumn('lsq_journey_id_mappings', 'lsq_substage'))
            {
                $table->string('lsq_substage')->nullable();
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
            if (Schema::hasColumn('lsq_journey_id_mappings', 'lsq_substage'))
            {
                $table->dropColumn('lsq_substage');
            }
        });
    }
}
