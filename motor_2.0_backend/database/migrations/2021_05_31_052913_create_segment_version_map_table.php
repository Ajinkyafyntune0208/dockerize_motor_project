<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSegmentVersionMapTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('segment_version_map', function (Blueprint $table) {
            $table->integer('segment_version_map_id', true);
            $table->string('segment_name', 50)->nullable();
            $table->integer('version_id')->nullable();
            $table->integer('broker_id')->nullable();
            $table->integer('ic_id')->nullable();
            $table->string('status')->nullable();
            $table->integer('created_by')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('updated_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('segment_version_map');
    }
}
