<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PosServiceRequestResponseDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('pos_service_request_response_data'))
        {
            Schema::create('pos_service_request_response_data', function (Blueprint $table) {
                $table->id();
                $table->integer('agent_id')->nullable();
                $table->string('company')->nullable();
                $table->string('section')->nullable();
                $table->string('method_name')->nullable();
                $table->string('product')->nullable();
                $table->string('method')->nullable();
                $table->longText('request')->nullable();
                $table->longText('response')->nullable();
                $table->longText('endpoint_url')->nullable();
                $table->string('ip_address')->nullable();
                $table->dateTime('start_time')->nullable();
                $table->dateTime('end_time')->nullable();
                $table->string('response_time')->nullable();
                $table->string('transaction_type')->nullable();
                $table->longText('headers')->nullable();
                $table->string('status')->nullable();
                $table->longText('message')->nullable();;
                $table->string('responsible')->nullable();
                $table->timestamps();
                
                // Indexes
                $table->index('transaction_type');
                
                // Constraint
                $table->json('headers')->nullable()->default(null)->change();
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
