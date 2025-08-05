<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLsqActivitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ( ! Schema::hasTable('lsq_activities'))
        {
            Schema::create('lsq_activities', function (Blueprint $table) {
                $table->id();
                $table->string('enquiry_id')->nullable();
                $table->string('lead_id')->nullable();
                $table->string('stage')->nullable();
                $table->string('activity_id')->nullable();
                $table->timestamps();
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
        Schema::dropIfExists('lsq_activities');
    }
}
