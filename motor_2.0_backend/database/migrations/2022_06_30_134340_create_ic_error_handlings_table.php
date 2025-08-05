<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIcErrorHandlingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ic_error_handlings', function (Blueprint $table) {
            $table->id();
            $table->string('company_alias')->nullable();
            $table->string('section')->nullable();
            $table->text('ic_error')->nullable();
            $table->text('custom_error')->nullable();
            $table->enum('status', array('Y', 'N'))->default('N');
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
        Schema::dropIfExists('ic_error_handlings');
    }
}
