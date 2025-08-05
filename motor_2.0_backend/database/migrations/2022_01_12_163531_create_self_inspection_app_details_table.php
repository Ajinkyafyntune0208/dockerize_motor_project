<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSelfInspectionAppDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('self_inspection_app_details', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_product_journey_id')->nullable();
            $table->string('mobile_no')->nullable();
            $table->string('regisration_no')->nullable();
            $table->string('video_url')->nullable();
            $table->timestamps();


            $table->foreign('user_product_journey_id')
                ->references('user_product_journey_id')
                ->on('user_product_journey');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('self_inspection_app_details');
    }
}
