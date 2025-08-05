<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBrokerDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('broker_details', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('frontend_url')->nullable();
            $table->string('backend_url')->nullable();
            $table->enum('environment',['uat', 'preprod', 'prod'])->nullable();
            $table->enum('status',['active','inactive'])->nullable();
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
        Schema::dropIfExists('broker_details');
    }
}
