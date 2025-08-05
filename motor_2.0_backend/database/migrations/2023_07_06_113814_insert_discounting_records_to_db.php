<?php

use App\Models\AgentMasterDiscount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InsertDiscountingRecordsToDb extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('permissions')->insert([
            'name' => 'discount.config',
            'guard_name' => 'web'
        ]);

        AgentMasterDiscount::insert([
            [
                'discount_name' => 'Global Configuration',
                'discount_code' => 'global_configuration'
            ],
            [
                'discount_name' => 'Vehicle and IC wise configuration',
                'discount_code' => 'vehicle_and_ic_wise'
            ],
            [
                'discount_name' => 'Vehicle Wise configuration',
                'discount_code' => 'vehicle_wise'
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
        
    }
}
