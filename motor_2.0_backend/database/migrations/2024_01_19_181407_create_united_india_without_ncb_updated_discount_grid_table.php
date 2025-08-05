<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUnitedIndiaWithoutNcbUpdatedDiscountGridTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('united_india_without_ncb_updated_discount_grid');
        
        Schema::create('united_india_without_ncb_updated_discount_grid', function (Blueprint $table) {
            $table->string('product');
            $table->string('age');
            $table->string('rto_state_ut');
            $table->string('rto_city_location');
            $table->integer('alto');
            $table->integer('alto_K10');
            $table->integer('a_star');
            $table->integer('baleno_diesel');
            $table->integer('baleno_petrol');
            $table->integer('celerio_diesel');
            $table->integer('celerio_petrol');
            $table->integer('ciaz_diesel');
            $table->integer('ciaz_petrol');
            $table->integer('dzire_diesel');
            $table->integer('dzire_petrol');
            $table->integer('eeco_diesel');
            $table->integer('eeco_petrol');
            $table->integer('versa__diesel');
            $table->integer('versa_Petrol');
            $table->integer('ertiga_diesel');
            $table->integer('ertiga_petrol');
            $table->integer('esteem_diesel');
            $table->integer('esteem_petrol');
            $table->integer('fronx');
            $table->integer('grand_Vitara');
            $table->integer('gypsy');
            $table->integer('ignis_diesel');
            $table->integer('ignis_petrol');
            $table->integer('invicto');
            $table->integer('jimny');
            $table->integer('kizashi');
            $table->integer('old_Baleno');
            $table->integer('omni');
            $table->integer('ritz_diesel');
            $table->integer('ritz_petrol');
            $table->integer('s_Cross_diesel');
            $table->integer('s_Cross_petrol');
            $table->integer('s_Presso');
            $table->integer('super_carry_diesel');
            $table->integer('super_carry_petrol');
            $table->integer('swift_diesel');
            $table->integer('swift_petrol');
            $table->integer('sx4_diesel');
            $table->integer('sx4_petrol');
            $table->integer('vitara_brezza_diesel');
            $table->integer('vitara_brezza_petrol');
            $table->integer('wagon_R');
            $table->integer('xL6');
            $table->integer('zen');
            $table->integer('zen_diesel');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('united_india_without_ncb_updated_discount_grid');
    }
}
