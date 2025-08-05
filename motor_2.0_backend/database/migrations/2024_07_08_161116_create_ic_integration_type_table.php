<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIcIntegrationTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ic_integration_type', function (Blueprint $table) {
            $table->id();
          
            $table->string('ic_alias', 150);
            $table->string('integration_type', 50);
            $table->string('segment', 50);
            $table->string('business_type', 100);
            $table->string('slug', 350);
            $table->timestamps();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ic_integration_type');
    }
}
