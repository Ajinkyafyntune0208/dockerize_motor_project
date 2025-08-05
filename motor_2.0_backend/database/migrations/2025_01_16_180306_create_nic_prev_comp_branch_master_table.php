<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNicPrevCompBranchMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nic_prev_comp_branch_master', function (Blueprint $table) {
            $table->bigInteger('party_code');
            $table->integer('party_id');
            $table->integer('role_id');
            $table->integer('parent_id');
            $table->string('party_name');
            $table->integer('creating_organ');
            $table->string('company_branch');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nic_prev_comp_branch_master');
    }
}