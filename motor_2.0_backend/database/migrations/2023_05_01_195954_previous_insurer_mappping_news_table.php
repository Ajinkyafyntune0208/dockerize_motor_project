<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PreviousInsurerMapppingNewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('previous_insurer_mappping_new', function (Blueprint $table) {
            $table->string("previous_insurer")->nullable(); 
            $table->string("company_alias")->nullable(); 
            $table->string("oriental")->nullable(); 
            $table->string("acko")->nullable(); 
            $table->string("sbi")->nullable(); 
            $table->string("bajaj_allianz")->nullable(); 
            $table->string("bharti_axa")->nullable(); 
            $table->string("cholamandalam")->nullable(); 
            $table->string("dhfl")->nullable(); 
            $table->string("edelweiss")->nullable(); 
            $table->string("future_generali")->nullable(); 
            $table->string("godigit")->nullable(); 
            $table->string("hdfc_ergo")->nullable(); 
            $table->string("hdfc")->nullable(); 
            $table->string("hdfc_ergo_gic")->nullable(); 
            $table->string("icici_lombard")->nullable(); 
            $table->string("iffco_tokio")->nullable(); 
            $table->string("kotak")->nullable(); 
            $table->string("liberty_videocon")->nullable(); 
            $table->string("magma")->nullable(); 
            $table->string("national_insurance")->nullable(); 
            $table->string("raheja")->nullable(); 
            $table->string("reliance")->nullable(); 
            $table->string("royal_sundaram")->nullable(); 
            $table->string("shriram")->nullable(); 
            $table->string("tata_aig")->nullable(); 
            $table->string("united_india")->nullable(); 
            $table->string("universal_sompo")->nullable(); 
            $table->string("new_india")->nullable(); 
            $table->string("nic")->nullable(); 
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
