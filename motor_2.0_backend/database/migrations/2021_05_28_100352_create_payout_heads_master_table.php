<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayoutHeadsMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payout_heads_master', function (Blueprint $table) {
            $table->integer('payout_head_id', true);
            $table->string('head_name', 50);
            $table->string('head_code', 50)->nullable();
            $table->string('status')->default('Y');
            $table->dateTime('createdon')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payout_heads_master');
    }
}
