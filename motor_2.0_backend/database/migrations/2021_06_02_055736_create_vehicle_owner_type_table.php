<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehicleOwnerTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicle_owner_type', function (Blueprint $table) {
            $table->integer('owner_type_id', true);
            $table->string('owner_type', 50)->default('');
            $table->string('status')->default('yes');
            $table->enum('owner_code',['I', 'C'])->default('I');
            $table->integer('created_by');
            $table->dateTime('created_on')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vehicle_owner_type');
    }
}
