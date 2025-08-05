<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIciciIllocationMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Check if the table doesn't exist before creating it
        if (!Schema::hasTable('icici_illocation_master')) {
            Schema::create('icici_illocation_master', function (Blueprint $table) {
                $table->id();
                $table->string('city_name')->nullable();
                $table->string('state_name')->nullable();
                $table->string('il_location_name')->nullable();
                $table->string('license_number')->nullable();
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
        Schema::dropIfExists('icici_illocation_master');
    }
}
