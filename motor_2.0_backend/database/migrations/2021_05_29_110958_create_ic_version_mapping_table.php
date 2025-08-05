<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIcVersionMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ic_version_mapping', function (Blueprint $table) {
            $table->integer('ic_version_mapping_id', true);
            $table->integer('fyn_version_id');
            $table->string('ic_version_code')->nullable();
            $table->integer('ic_id');
            $table->string('idv', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ic_version_mapping');
    }
}
