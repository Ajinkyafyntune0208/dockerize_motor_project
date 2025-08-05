<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterRtoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_rto', function (Blueprint $table) {
            $table->integer('rto_id', true);
            $table->integer('rto_group_id')->index('fk_group_rto_id');
            $table->integer('state_id')->index('fk_states');
            $table->integer('zone_id')->index('fk_zone_id');
            $table->string('rto_code', 50);
            $table->string('rto_number', 50);
            $table->string('rto_name', 500);
            $table->string('status')->default('Active');
            $table->integer('created_by')->default(1);
            $table->dateTime('created_date')->useCurrent();
            $table->integer('updated_by')->nullable();
            $table->dateTime('updated_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_rto');
    }
}
