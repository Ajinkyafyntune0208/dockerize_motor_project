<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsInLsqJourneyIdMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lsq_journey_id_mappings', function (Blueprint $table) {
            if ( ! Schema::hasColumns('lsq_journey_id_mappings', ['first_name', 'last_name', 'email', 'phone']))
            {
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
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
            if (Schema::hasColumns('lsq_journey_id_mappings', ['first_name', 'last_name', 'email', 'phone']))
            {
                $table->dropColumn('first_name');
                $table->dropColumn('last_name');
                $table->dropColumn('email');
                $table->dropColumn('phone');
            }
        });
    }
}
