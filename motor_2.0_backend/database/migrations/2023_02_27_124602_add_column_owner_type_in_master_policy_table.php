<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnOwnerTypeInMasterPolicyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('master_policy', function (Blueprint $table) {
            //
            if(!Schema::hasColumn('master_policy', 'owner_type')) {
                $table->string('owner_type',5)->after('pos_flag')->default('I,C');
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
            //
            if(Schema::hasColumn('master_policy', 'owner_type')) {
                $table->dropColumn('owner_type');
            }
        });
    }
}
