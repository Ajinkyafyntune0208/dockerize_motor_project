<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterBundleAddonTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_bundle_addon', function (Blueprint $table) {
            $table->integer('master_bundle_id', true);
            $table->string('bundle_addon_name', 100);
            $table->integer('master_policy_id')->nullable();
            $table->string('status');
            $table->integer('created_by')->nullable();
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
        Schema::dropIfExists('master_bundle_addon');
    }
}
