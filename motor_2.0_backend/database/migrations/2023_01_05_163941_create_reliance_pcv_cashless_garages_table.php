<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReliancePcvCashlessGaragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('reliance_pcv_cashless_garage')) 
        {
            Schema::create('reliance_pcv_cashless_garage', function (Blueprint $table) {
                $table->string('garage_name','83');
                $table->string('Vehicle_type','16');
                $table->string('city_name','34');
                $table->string('pincode','6');
                $table->string('mobile','10');
                $table->string('address','378');
            });
        }
        //
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reliance_pcv_cashless_garage');
    }
}
