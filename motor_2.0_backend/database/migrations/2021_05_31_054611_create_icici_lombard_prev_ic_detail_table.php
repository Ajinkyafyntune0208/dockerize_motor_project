<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIciciLombardPrevIcDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('icici_lombard_prev_ic_detail', function (Blueprint $table) {
            $table->integer('company_id', true);
            $table->string('company_name', 100)->nullable();
            $table->string('company_code', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('icici_lombard_prev_ic_detail');
    }
}
