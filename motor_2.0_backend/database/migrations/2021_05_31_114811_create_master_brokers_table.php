<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterBrokersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_brokers', function (Blueprint $table) {
            $table->integer('broker_id', true);
            $table->string('broker_name', 100);
            $table->string('broker_shortname', 100);
            $table->bigInteger('broker_contact');
            $table->string('broker_address');
            $table->string('broker_email');
            $table->string('url', 100);
            $table->integer('broker_year_started');
            $table->string('logo');
            $table->string('status')->default('Active');
            $table->integer('created_by');
            $table->dateTime('created_date');
            $table->integer('updated_by');
            $table->dateTime('updated_date')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_brokers');
    }
}
