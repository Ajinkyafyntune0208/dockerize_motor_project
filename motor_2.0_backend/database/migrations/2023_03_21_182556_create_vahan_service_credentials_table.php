<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVahanServiceCredentialsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('vahan_service_credentials')) {
            Schema::dropIfExists('vahan_service_credentials');
        }
            Schema::create('vahan_service_credentials', function (Blueprint $table) {
                $table->id();
                $table->integer('vahan_service_id');
                $table->string('label');
                $table->string('key');
                $table->string('value');
                $table->enum('status', ['Active', 'Inactive'])->default('Active');
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
        Schema::dropIfExists('vahan_service_credentials');
    }
}
