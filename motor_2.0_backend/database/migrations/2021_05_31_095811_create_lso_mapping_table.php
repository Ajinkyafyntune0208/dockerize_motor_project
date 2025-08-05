<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLsoMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lso_mapping', function (Blueprint $table) {
            $table->integer('lso_mapping_id', true);
            $table->string('branch_id', 50)->nullable();
            $table->string('city', 50)->nullable();
            $table->string('rto_code', 50)->nullable();
            $table->integer('corp_id')->nullable();
            $table->integer('created_by')->nullable();
            $table->dateTime('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lso_mapping');
    }
}
