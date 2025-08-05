<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FastlanePreviousIcMapping extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fastlane_previous_ic_mapping', function (Blueprint $table) {
            $table->id()->autoIncrement();
            $table->string('company_name', 100)->nullable();
            $table->string('company_alias', 100)->nullable();
            $table->string('identifier', 50)->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('fastlane_previous_ic_mapping');
    }
}
