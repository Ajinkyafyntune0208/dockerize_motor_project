<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAceTokenRoleDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ace_token_role_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_product_journey_id')->nullable();
            $table->string('role')->nullable();
            $table->string('lead_id')->nullable();
            $table->longText('token')->nullable();
            $table->longText('response')->nullable();
            $table->longText('lead_response')->nullable();
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
        Schema::dropIfExists('ace_token_role_data');
    }
}
