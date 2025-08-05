<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePremCalcPlaceholdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prem_calc_placeholders', function (Blueprint $table) {
            $table->id();
            $table->string('placeholder_name', 250)->nullable();
            $table->string('placeholder_key', 250)->nullable();
            $table->string('placeholder_value', 250)->default('#');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('created_by');
            $table->integer('updated_by')->nullable();

            $table->index('deleted_at');
            $table->index('placeholder_name');
            $table->index('placeholder_key');
            $table->index('placeholder_value');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prem_calc_placeholders');
    }
}
