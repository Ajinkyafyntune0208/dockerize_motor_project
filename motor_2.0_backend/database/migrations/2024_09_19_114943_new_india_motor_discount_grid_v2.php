<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NewIndiaMotorDiscountGridV2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('new_india_motor_discount_grid_v2', function (Blueprint $table) {
            $table->string('section'); 
            $table->string('discount_percent_without_addons_0_to_120_months')->nullable();
            $table->string('discount_percent_without_addons_121_to_178_months')->nullable();
            $table->string('discount_percent_with_addons_0_to_36_months')->nullable();
            $table->string('discount_percent_with_addons_37_to_58_months')->nullable();
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
        });
        DB::table('new_india_motor_discount_grid_v2')->insert([
            [
                'section' => 'SS',
                'discount_percent_without_addons_0_to_120_months' => '50',
                'discount_percent_without_addons_121_to_178_months' => '50',
                'discount_percent_with_addons_0_to_36_months' => '50',
                'discount_percent_with_addons_37_to_58_months' => '50',
            ],
            [
                'section' => 'SQ',
                'discount_percent_without_addons_0_to_120_months' => '50',
                'discount_percent_without_addons_121_to_178_months' => '50',
                'discount_percent_with_addons_0_to_36_months' => '50',
                'discount_percent_with_addons_37_to_58_months' => '50',
            ],
            [
                'section' => 'TW',
                'discount_percent_without_addons_0_to_120_months' => '70',
                'discount_percent_without_addons_121_to_178_months' => '0',
                'discount_percent_with_addons_0_to_36_months' => '70',
                'discount_percent_with_addons_37_to_58_months' => '0',
            ],
            [
                'section' => 'PC',
                'discount_percent_without_addons_0_to_120_months' => '70',
                'discount_percent_without_addons_121_to_178_months' => '0',
                'discount_percent_with_addons_0_to_36_months' => '70',
                'discount_percent_with_addons_37_to_58_months' => '0',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('new_india_motor_discount_grid_v2');
    }
}
