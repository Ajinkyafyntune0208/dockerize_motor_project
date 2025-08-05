<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRtoCityNamesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('rto_city_names')) 
        {
            Schema::create('rto_city_names', function (Blueprint $table) {
                $table->id();
                $table->string('rto_city_name');
                $table->unsignedBigInteger('rto_id');
                $table->foreign('rto_id')
                    ->references('id')
                    ->on('rto_counts')
                    ->onDelete('cascade') 
                    ->onUpdate('cascade');

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
        Schema::dropIfExists('rto_city_names');
    }
}
