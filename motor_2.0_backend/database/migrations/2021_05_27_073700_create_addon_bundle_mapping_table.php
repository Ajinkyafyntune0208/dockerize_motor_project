<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAddonBundleMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('addon_bundle_mapping', function (Blueprint $table) {
            $table->integer('mapping_id', true);
            $table->integer('master_bundle_id')->nullable();
            $table->integer('addon_id')->nullable();
            $table->integer('master_policy_id')->nullable();
            $table->string('status')->nullable()->default('Active');
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
        Schema::dropIfExists('addon_bundle_mapping');
    }
}
