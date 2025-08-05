<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterSalutationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_salutation', function (Blueprint $table) {
            $table->integer('salutation_id', true);
            $table->string('salutation_name', 50)->nullable();
            $table->string('salutation_code', 10)->nullable();
            $table->string('status')->nullable()->default('Y');
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
        Schema::dropIfExists('master_salutation');
    }
}
