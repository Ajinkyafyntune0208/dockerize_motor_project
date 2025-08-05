<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;


class CreateOrientalCvRtoMastersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!(Schema::hasTable('oriental_cv_rto_masters'))) {
            Schema::create('oriental_cv_rto_masters', function (Blueprint $table) {
                $table->id();
                $table->string('rto_zone', 10)->nullable();
                $table->string('rto_description', 255)->nullable();
                $table->string('rto_code', 10)->nullable();
            });
            Artisan::call('db:seed --class=OrientalCvRtoMasterSeeder');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('oriental_cv_rto_masters');
    }
}
