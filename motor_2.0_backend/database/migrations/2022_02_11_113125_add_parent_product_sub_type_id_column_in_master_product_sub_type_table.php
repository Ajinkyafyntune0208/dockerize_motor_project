<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddParentProductSubTypeIdColumnInMasterProductSubTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('master_product_sub_type', function (Blueprint $table) {
            $table->integer('parent_product_sub_type_id')->nullable()->after('parent_id');

            $table->foreign('parent_product_sub_type_id')->references('product_sub_type_id')->on('master_product_sub_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_product_sub_type', function (Blueprint $table) {
            $table->dropColumn('parent_product_sub_type_id');
        });
    }
}
