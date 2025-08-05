<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterHomeViewTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_home_view', function (Blueprint $table) {
            $table->integer('home_view_id', true);
            $table->integer('product_sub_type_id')->nullable();
            $table->string('image_url', 50);
            $table->string('redirect_url', 500)->nullable();
            $table->string('status')->default('Y');
            $table->dateTime('createdon')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_home_view');
    }
}
