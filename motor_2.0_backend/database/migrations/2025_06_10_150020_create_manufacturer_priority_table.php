<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateManufacturerPriorityTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(Schema::hasTable('manufacturer_priorties')) return;
        Schema::create('manufacturer_priorties', function (Blueprint $table) {
            $table->id();
            $table->enum('sellerType',['B2B','B2C']);
            $table->string('vehicleType');
            $table->enum('cv_type',['GCV','PCV'])->nullable()->default(null);
            $table->integer('priority');
            $table->string('insurer');
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
        Schema::dropIfExists('manufacturer_priorties');
    }
}
