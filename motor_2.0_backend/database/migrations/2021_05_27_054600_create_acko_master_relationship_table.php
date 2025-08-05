<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAckoMasterRelationshipTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('acko_master_relationship', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('relationship', 256);
            $table->string('status', 256)->nullable();
            $table->integer('product_sub_type_id');
            $table->integer('ic_id');
            $table->string('name', 256)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('acko_master_relationship');
    }
}
