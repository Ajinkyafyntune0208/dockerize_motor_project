<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CpaTenure extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cpa_tenure', function (Blueprint $table) {
            $table->id();
            $table->string('company_alias')->nullable();
            $table->string('product_type')->nullable();
            $table->integer('cpa_term_1')->nullable();
            $table->integer('cpa_term_3')->nullable();
            $table->integer('cpa_term_5')->nullable();
            $table->boolean('status')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cpa_tenure');
    }
}
