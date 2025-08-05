<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVahanImportExcelLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vahan_import_excel_logs', function (Blueprint $table) {
            $table->id();
            $table->string('unique_id')->nullable();
            $table->string('user_email');
            $table->string('file_path')->nullable();
            $table->string('start_date');
            $table->string('end_date');
            $table->enum('status', ['0', '1'])->default('0');
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
        Schema::dropIfExists('vahan_import_excel_logs');
    }
}
