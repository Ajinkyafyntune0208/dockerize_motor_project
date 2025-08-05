<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterCarInsuranceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_car_insurance', function (Blueprint $table) {
            $table->increments('car_insu_id');
            $table->unsignedInteger('company_id')->index('FK_master_car_insurance_master_company');
            $table->unsignedInteger('product_id')->index('FK_master_car_insurance_master_product');
            $table->text('owner_type')->comment('I:Individual, C:Company');
            $table->text('insurance_type')->comment('N:New, R:Renew, B:Breakin, U:Used');
            $table->text('product_type');
            $table->text('premium_type')->nullable();
            $table->unsignedInteger('zero_dep')->comment('0:Zero dep,1:Regular');
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_car_insurance');
    }
}
