<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Createmagmaposregid extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
    
        if (Schema::hasTable('magma_pos_mapping')) {
            //drop
            Schema::dropIfExists('magma_pos_mapping');
            // create
            Schema::create('magma_pos_mapping', function (Blueprint $table) {
                $table->id(); 
                $table->integer('agent_id');
                $table->string('mhdipospcode')->nullable();
                $table->text('request')->nullable();
                $table->text('response')->nullable();
                $table->text('status')->nullable();
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
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
        //
    }
}
