<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBranchTerritoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('branch_territory', function (Blueprint $table) {
            $table->integer('branch_id', true);
            $table->string('corp_name', 50);
            $table->string('city', 50);
            $table->integer('state_id')->nullable();
            $table->integer('district_id')->nullable();
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
        Schema::dropIfExists('branch_territory');
    }
}
