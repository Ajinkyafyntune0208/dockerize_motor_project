<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTataAigV2SpecialRegnoPcvTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
        Schema::create('tata_aig_v2_special_regno_pcv', function (Blueprint $table) {
            $table->id();
            $table->string('rto');
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
        Schema::dropIfExists('tata_aig_v2_special_regno_pcv');
    }
}
