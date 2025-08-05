<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAddonBundleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('addon_bundle', function (Blueprint $table) {
            $table->integer('addon_bundle_id', true);
            $table->integer('master_policy_id');
            $table->integer('addon_id');
            $table->string('bundle_name', 100);
            $table->string('status', 10);
            $table->integer('created_by');
            $table->integer('updated_by');
            $table->dateTime('created_date')->nullable();
            $table->dateTime('updated_date')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('addon_bundle');
    }
}
