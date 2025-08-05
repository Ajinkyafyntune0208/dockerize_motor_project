<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGcvCarrierTypeInMasterPolicyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('master_policy', function (Blueprint $table) {
            $table->text('gcv_carrier_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_policy', function (Blueprint $table) {
            $table->dropColumn('gcv_carrier_type');
        });
    }
}
