<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNomineeRelationshipNewTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('nominee_relationship_new')) {
            Schema::dropIfExists('nominee_relationship_new');
        }
        Schema::create('nominee_relationship_new', function (Blueprint $table) {
            $table->id();
            $table->string('relation_name');
            $table->string('bajaj_allianz')->nullable();
            $table->string('hdfc_ergo')->nullable();
            $table->string('iffco_tokio')->nullable();
            $table->string('new_india')->nullable();
            $table->string('reliance')->nullable();
            $table->string('tata_aig')->nullable();
            $table->string('united_india')->nullable();
            $table->string('bharti_axa')->nullable();
            $table->string('future_generali')->nullable();
            $table->string('universal_sompo')->nullable();
            $table->string('cholla_mandalam')->nullable();
            $table->string('liberty_videocon')->nullable();
            $table->string('shriram')->nullable();
            $table->string('sbi')->nullable();
            $table->string('royal_sundaram')->nullable();
            $table->string('godigit')->nullable();
            $table->string('kotak')->nullable();
            $table->string('acko')->nullable();
            $table->string('icici_lombard')->nullable();
            $table->string('magma')->nullable();
            $table->string('raheja')->nullable();
            $table->string('edelweiss')->nullable();
            $table->string('oriental')->nullable();
            $table->string('nic')->nullable();
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
        Schema::dropIfExists('nominee_relationship_new');
    }
}
