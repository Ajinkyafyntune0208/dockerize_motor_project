<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterRegionalOfficeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_regional_office', function (Blueprint $table) {
            $table->integer('regional_office_id', true);
            $table->integer('corporate_office_id');
            $table->integer('zone_office_id');
            $table->string('regional_office_name', 100);
            $table->string('regional_office_desc');
            $table->string('status')->default('Active');
            $table->string('created_by', 100);
            $table->dateTime('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_regional_office');
    }
}
