<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexInLsqActivitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lsq_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('enquiry_id')->change();
            $table->index('enquiry_id');
        });

        Schema::table('lsq_journey_id_mappings', function (Blueprint $table) {
            $table->unsignedBigInteger('enquiry_id')->change();
            $table->index('enquiry_id');
        });

        Schema::table('lsq_service_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('enquiry_id')->change();
            $table->index('enquiry_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lsq_activities', function (Blueprint $table) {
            $table->dropIndex('enquiry_id');
        });

        Schema::table('lsq_journey_id_mappings', function (Blueprint $table) {
            $table->dropIndex('enquiry_id');
        });

        Schema::table('lsq_service_logs', function (Blueprint $table) {
            $table->dropIndex('enquiry_id');
        });
    }
}
