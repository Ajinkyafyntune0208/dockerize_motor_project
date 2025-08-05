<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterIcRtoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_ic_rto', function (Blueprint $table) {
            $table->integer('ic_rto_id', true);
            $table->integer('rto_id')->nullable();
            $table->string('rto_code', 50)->nullable();
            $table->string('rto_name', 10)->nullable();
            $table->string('rto_state', 10)->nullable();
            $table->integer('company_id')->nullable();
            $table->string('status')->nullable()->default('Active');
            $table->integer('created_by')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('updated_date')->nullable();
            $table->integer('deleted_by')->nullable();
            $table->dateTime('deleted_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_ic_rto');
    }
}
