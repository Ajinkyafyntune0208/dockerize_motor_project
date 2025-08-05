<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTableMasterCommunicationLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_communication_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_product_journey_id')->unsigned()->nullable();
            $table->bigInteger('old_user_product_journey_id')->unsigned()->nullable();
            $table->date('prev_policy_end_end')->nullable()->default(null);
            $table->enum('service_type', ['WHATSAPP', 'SMS', 'EMAIL', 'NA'])->default('NA');
            $table->text('request')->nullable();
            $table->text('response')->nullable();
            $table->tinyInteger('days')->nullable();
            $table->enum('communication_module', ['NEW', 'RENEWAL', 'ROLLOVER', 'OTHER'])->default('OTHER');
            $table->enum('status', ['Y', 'N'])->default('N');
            $table->timestamps();
            $table->index('user_product_journey_id');
            $table->index('old_user_product_journey_id');
            $table->index('prev_policy_end_end');
            $table->index('service_type');
            $table->index('communication_module');
            $table->fullText('request');            
            $table->fullText('response');            
            $table->index('days');            
            $table->index('status');            
            $table->index('created_at');            
            $table->index('updated_at');            
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('table_master_communication_logs');
    }
}
