<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class VahanServiceLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('vahan_service_logs')) {
            Schema::create('vahan_service_logs', function (Blueprint $table) {
                $table->id();
                $table->integer('enquiry_id');
                $table->string("vehicle_reg_no", 50);
                $table->string("stage", 10);
                $table->text("request");
                $table->longText("response");
                $table->string("status", 10);
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
        //
    }
}
