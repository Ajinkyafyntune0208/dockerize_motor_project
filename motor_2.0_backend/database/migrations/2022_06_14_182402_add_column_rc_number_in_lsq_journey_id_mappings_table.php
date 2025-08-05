<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnRcNumberInLsqJourneyIdMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lsq_journey_id_mappings', function (Blueprint $table) {
            if ( ! Schema::hasColumn('lsq_journey_id_mappings', 'rc_number'))
            {
                $table->string('rc_number')->nullable();
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
            if (Schema::hasColumn('lsq_journey_id_mappings', 'rc_number'))
            {
                $table->dropColumn('rc_number');
            }
        });
    }
}
