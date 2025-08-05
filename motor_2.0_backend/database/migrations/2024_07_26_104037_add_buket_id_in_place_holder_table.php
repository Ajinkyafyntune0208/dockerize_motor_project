<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBuketIdInPlaceHolderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('prem_calc_placeholders', function (Blueprint $table) {
            $table->bigInteger('prem_calc_bucket_id')->nullable()->after('id');

            $table->index('prem_calc_bucket_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('prem_calc_placeholders', function (Blueprint $table) {
            //
        });
    }
}
