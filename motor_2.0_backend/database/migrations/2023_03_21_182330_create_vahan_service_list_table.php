<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVahanServiceListTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('vahan_service_list')) {
            Schema::dropIfExists('vahan_service_list');
        }
        Schema::create('vahan_service_list', function (Blueprint $table) {
            $table->id();
            $table->string('vahan_service_name')->unique();
            $table->string('vahan_service_name_code')->unique();
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
        Schema::dropIfExists('vahan_service_list');
    }
}
