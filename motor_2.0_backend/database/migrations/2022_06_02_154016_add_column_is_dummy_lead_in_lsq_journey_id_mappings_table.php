<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIsDummyLeadInLsqJourneyIdMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lsq_journey_id_mappings', function (Blueprint $table) {
            if ( ! Schema::hasColumn('lsq_journey_id_mappings', 'is_dummy_lead'))
            {
                $table->integer('is_dummy_lead')->nullable()->default(0);
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
            if (Schema::hasColumn('is_dummy_lead'))
            {
                $table->dropColumn('is_dummy_lead');
            }
        });
    }
}
