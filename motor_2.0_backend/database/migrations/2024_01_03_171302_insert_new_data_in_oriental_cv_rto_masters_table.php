<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InsertNewDataInOrientalCvRtoMastersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ((Schema::hasTable('oriental_cv_rto_masters'))) {
            \Illuminate\Support\Facades\Artisan::call('db:seed --class=OrientalCvRtoMasterSeeder');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('oriental_cv_rto_masters', function (Blueprint $table) {
            //
        });
    }
}
