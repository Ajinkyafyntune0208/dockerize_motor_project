<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Database\Seeders\IciciLombardAddonsAgeValidations;

class CreateIciciLombardAddonsValidationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('icici_lombard_addons_validation', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('premium_vehicle_age');
            $table->integer('non_premium_vehicle_age');
            $table->timestamps();
        });

        $seeder = new IciciLombardAddonsAgeValidations();
        $seeder->run();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('icici_lombard_addons_validation');
    }
}
