<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateTableLeadGenerationLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('lead_generation_logs')) 
        {            
            Schema::table('lead_generation_logs', function (Blueprint $table) 
            {
                $table->string('method', 255)->nullable()->after('response');
                $table->string('url', 255)->nullable()->after('method');
                $table->smallInteger('step')->nullable()->after('url');
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
