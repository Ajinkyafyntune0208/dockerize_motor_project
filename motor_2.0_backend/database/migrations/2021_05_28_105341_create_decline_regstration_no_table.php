<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeclineRegstrationNoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('decline_regstration_no', function (Blueprint $table) {
            $table->integer('vehicle_id', true);
            $table->string('vehicle_registration_no', 50);
            $table->string('status')->nullable()->default('Y');
            $table->string('vehicle_status')->nullable();
            $table->dateTime('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('decline_regstration_no');
    }
}
