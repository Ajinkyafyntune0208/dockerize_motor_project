<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMdmSyncLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mdm_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('master_id');
            $table->string('master_name', 255)->nullable();
            $table->integer('total_rows')->default(0)->nullable();
            $table->integer('rows_inserted')->default(0)->nullable();
            $table->enum('status', ['success', 'failure', 'pending']);
            $table->text('message');
            $table->timestamps();
            $table->index('updated_at');
            $table->index('status');
            $table->index('master_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mdm_sync_logs');
    }
}
