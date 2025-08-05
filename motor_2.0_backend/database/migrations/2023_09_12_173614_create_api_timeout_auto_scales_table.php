<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApiTimeoutAutoScalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('api_timeout_auto_scales', function (Blueprint $table) {
            $table->id();
            $table->string('company_alias', 50);
            $table->string('transaction_type', 20);
            $table->integer('timeout');
            $table->timestamps();
            $table->index(['company_alias', 'transaction_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('api_timeout_auto_scales');
    }
}
