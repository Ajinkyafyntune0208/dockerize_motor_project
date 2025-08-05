<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexColumnAndPermissionForUserJourneyAcctivities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_journey_activities', function (Blueprint $table) {
            $table->index('user_product_journey_id');
        });

        DB::table('permissions')->updateOrInsert([
            'name' => 'user-journey-activities.clear'
        ],
        [
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now()
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
