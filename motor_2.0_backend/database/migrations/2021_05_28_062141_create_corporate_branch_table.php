<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCorporateBranchTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('corporate_branch', function (Blueprint $table) {
            $table->integer('corp_branch_id', true);
            $table->integer('corp_id');
            $table->integer('corp_type_id')->default(0);
            $table->string('corp_email', 50);
            $table->string('corp_contact', 10);
            $table->integer('pincode');
            $table->string('city', 50);
            $table->string('state', 50);
            $table->string('gst_number', 50);
            $table->string('corp_addres', 200);
            $table->dateTime('created_by')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('corporate_branch');
    }
}
