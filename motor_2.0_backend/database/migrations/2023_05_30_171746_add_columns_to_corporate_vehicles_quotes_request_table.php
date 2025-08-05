<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToCorporateVehiclesQuotesRequestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
            $table->enum('is_body_idv_changed', ['Y', 'N'])->default('N');
            $table->integer('edit_body_idv')->default(0);
            $table->enum('is_chassis_idv_changed', ['Y', 'N'])->default('N');
            $table->integer('edit_chassis_idv')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
            // $table->dropColumn(['is_body_idv_changed', 'edit_body_idv', 'is_chassis_idv_changed', 'edit_chassis_idv']);
        });
    }
}
