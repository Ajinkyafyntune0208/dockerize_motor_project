<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentsImdConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('agents_imd_configs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('agent_id')->nullable();
            $table->bigInteger('master_product_sub_type_id')->nullable();
            $table->bigInteger('ic_id')->nullable();
            $table->text('credentials')->nullable();
            $table->string('source', 20)->nullable();
            $table->boolean('status')->default(false);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
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
        Schema::dropIfExists('agents_imd_configs');
    }
}
