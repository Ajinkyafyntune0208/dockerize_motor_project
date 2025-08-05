<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGramcoverPostDataApisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gramcover_post_data_apis', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_product_journey_id')->nullable();
            $table->text('token')->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->string('status')->nullable();
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
        Schema::dropIfExists('gramcover_post_data_apis');
    }
}
