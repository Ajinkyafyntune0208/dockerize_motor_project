<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadStageMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lead_stage_master', function (Blueprint $table) {
            $table->integer('lead_stage_id', true);
            $table->string('lead_stage', 50)->nullable();
            $table->boolean('isactive')->nullable()->default(0);
            $table->dateTime('createdon')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lead_stage_master');
    }
}
