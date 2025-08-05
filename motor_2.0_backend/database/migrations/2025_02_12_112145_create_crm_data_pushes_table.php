<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCrmDataPushesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('crm_data_pushes', function (Blueprint $table) {
            $table->id();
            $table->longText('payload');
            $table->bigInteger('user_product_journey_id');
            $table->string('enquiry_id', 100);
            $table->string('lead_id', 100);
            $table->string('stage', 100);
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
        Schema::dropIfExists('crm_data_pushes');
    }
}
