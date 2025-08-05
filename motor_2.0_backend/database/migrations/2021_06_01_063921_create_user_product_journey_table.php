<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserProductJourneyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_product_journey', function (Blueprint $table) {
            $table->bigInteger('user_product_journey_id', true);
            $table->integer('product_sub_type_id')->nullable();
            $table->integer('corp_id')->nullable();
            $table->string('user_fname', 50)->nullable();
            $table->integer('user_id')->nullable();
            $table->string('user_mname', 50)->nullable();
            $table->string('user_lname', 50)->nullable();
            $table->string('user_email', 50)->nullable();
            $table->string('user_mobile', 50)->nullable();
            $table->string('status')->nullable()->default('yes');
            $table->integer('lead_stage_id')->nullable();
            $table->string('lead_source', 256)->nullable();
            $table->string('api_token')->nullable();
            $table->integer('created_by')->nullable();
            $table->dateTime('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_product_journey');
    }
}
