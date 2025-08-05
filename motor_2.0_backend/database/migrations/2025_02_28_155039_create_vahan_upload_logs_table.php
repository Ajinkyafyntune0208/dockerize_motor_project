<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVahanUploadLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vahan_upload_logs', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_reg_no');
            $table->enum('source', ['Online', 'Offline'])->default(null)->nullable();
            $table->longText('response');
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
        Schema::dropIfExists('vahan_uplord_logs');
    }
}
