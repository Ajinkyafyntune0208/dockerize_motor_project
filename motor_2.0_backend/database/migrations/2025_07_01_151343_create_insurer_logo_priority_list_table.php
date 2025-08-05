<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInsurerLogoPriorityListTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('insurer_logo_priority_list')){
        Schema::create('insurer_logo_priority_list', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->string('company_alias');
            $table->string('company_name');
            $table->enum('seller_type',['B2B','B2C']);
            $table->integer('priority');
            $table->timestamps();
        });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('insurer_logo_priority_list');
    }
}