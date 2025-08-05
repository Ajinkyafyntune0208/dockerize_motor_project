<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRehitRestrictEnqsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rehit_restrict_enqs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_product_journey_id')->unique()->nullable();
            $table->integer('attempts')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rehit_restrict_enqs');
    }
}
