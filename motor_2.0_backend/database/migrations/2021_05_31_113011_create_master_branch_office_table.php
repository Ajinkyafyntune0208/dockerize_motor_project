<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterBranchOfficeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_branch_office', function (Blueprint $table) {
            $table->integer('branch_office_id', true);
            $table->integer('corporate_office_id');
            $table->integer('regional_office_id');
            $table->string('branch_office_name');
            $table->string('branch_office_desc', 500);
            $table->string('branch_office_address', 500);
            $table->string('status')->default('Active');
            $table->string('created_by', 100);
            $table->dateTime('created_date');
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
        Schema::dropIfExists('master_branch_office');
    }
}
