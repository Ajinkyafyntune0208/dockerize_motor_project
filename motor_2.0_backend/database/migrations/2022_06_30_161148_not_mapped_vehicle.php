<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class NotMappedVehicle extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('not_mapped_vehicle')) 
        {
            Schema::create('not_mapped_vehicle', function (Blueprint $table) 
            {
                $table->id();
                $table->string('vehicle_code')->nullable();
                $table->string('vehicle_reg_no')->nullable();
                $table->string('manf')->nullable();
                $table->string('model')->nullable();
                $table->string('version')->nullable();
                $table->string('fuel')->nullable();
                $table->string('policy_no')->nullable();
                $table->string('source')->nullable();            
                $table->string('comments')->nullable();            
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));            
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
