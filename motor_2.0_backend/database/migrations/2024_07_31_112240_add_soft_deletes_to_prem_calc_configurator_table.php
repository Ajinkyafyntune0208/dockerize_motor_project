<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeletesToPremCalcConfiguratorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('prem_calc_configurator', function (Blueprint $table) {
            $table->softDeletes()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('prem_calc_configurator', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->change();

        });
    }
}