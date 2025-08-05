<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddProductSubTypeIdToMmvBlockerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('mmv_blocker')->truncate();
        Schema::table('mmv_blocker', function (Blueprint $table) {
            $table->integer('product_sub_type_id')->nullable()->after('seller_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('mmv_blocker', function (Blueprint $table) {
            $table->dropColumn('product_sub_type_id');
        });
    }
}
