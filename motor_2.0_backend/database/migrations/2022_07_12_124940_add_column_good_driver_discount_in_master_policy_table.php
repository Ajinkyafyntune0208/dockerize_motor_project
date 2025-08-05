<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnGoodDriverDiscountInMasterPolicyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('master_policy', function (Blueprint $table) {
            if ( ! Schema::hasColumn('master_policy', 'good_driver_discount'))
            {
                $table->enum('good_driver_discount', ['Yes', 'No'])->default('No')->after('zero_dep');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_policy', function (Blueprint $table) {
            if (Schema::hasColumn('master_policy', 'good_driver_discount'))
            {
                $table->dropColumn('good_driver_discount');
            }
        });
    }
}
