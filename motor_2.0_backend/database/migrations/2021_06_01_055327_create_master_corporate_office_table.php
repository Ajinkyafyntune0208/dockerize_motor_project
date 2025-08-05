<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterCorporateOfficeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_corporate_office', function (Blueprint $table) {
            $table->integer('corporate_office_id', true);
            $table->string('corporate_office_name', 50)->nullable();
            $table->string('corporate_office_desc', 200)->nullable();
            $table->string('status')->nullable()->default('Active');
            $table->string('created_by', 100)->nullable();
            $table->dateTime('created_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_corporate_office');
    }
}
