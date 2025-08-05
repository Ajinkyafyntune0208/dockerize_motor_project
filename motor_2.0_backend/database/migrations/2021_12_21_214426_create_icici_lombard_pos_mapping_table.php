<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIciciLombardPosMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('icici_lombard_pos_mapping', function (Blueprint $table) {
            $table->integer('ic_mapping_id', true); 
            $table->integer('agent_id');
            $table->text('im_id')->nullable();
            $table->text('request')->nullable();
            $table->text('response')->nullable();
            $table->text('status')->nullable();
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
        
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('icici_lombard_pos_mapping');
    }
}
