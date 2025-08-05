<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterOfficeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_office', function (Blueprint $table) {
            $table->integer('office_id', true);
            $table->integer('company_id');
            $table->string('office_type', 100);
            $table->string('status')->default('Active');
            $table->string('office_code', 20);
            $table->string('office_state', 30);
            $table->string('office_city', 50);
            $table->string('office_district', 50);
            $table->string('office_address');
            $table->integer('office_pincode');
            $table->integer('administrative_office_id');
            $table->integer('functional_office_id');
            $table->string('created_by', 100);
            $table->dateTime('created_at');
            $table->string('updated_by', 100);
            $table->dateTime('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_office');
    }
}
